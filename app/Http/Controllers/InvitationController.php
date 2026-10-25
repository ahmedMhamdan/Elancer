<?php

namespace App\Http\Controllers;

use App\Actions\Contracts\PublicReviews;
use App\Actions\Demo\DemoWorld;
use App\Actions\Invitations\InvitationLifecycle as Lifecycle;
use App\Enums\WorkspaceRole;
use App\Models\Invitation;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use App\Notifications\WorkspaceEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    /** Plan 032 part 14: where an invitation stands. With All they are the inbox's lists, and they add up to All. */
    private const LISTS = ['waiting', 'applied', 'declined', 'closed'];

    private function eligible(Request $request): void
    {
        abort_unless($request->user()?->canParticipateInMarketplace() && $request->user()->onboarding_completed_at, 403);
    }

    public function create(Request $request, Profile $profile): Response
    {
        $this->eligible($request);
        abort_unless(Profile::query()->publiclyVisible()->whereKey($profile->id)->exists(), 404);
        abort_if($profile->user_id === $request->user()->id, 403);
        // Plan 027: a demo account and a real member never hire each other.
        abort_if(DemoWorld::apart($request->user()->id, $profile->user_id), 403, __('Demo accounts and members work apart, so this is not available between them.'));
        $blocked = Lifecycle::blocked($profile->user_id, $request->user()->id);
        $projects = Project::query()->visible()->where('user_id', $request->user()->id)->where('status', 'published')->where('application_closes_at', '>', now())
            ->orderByDesc('id')->paginate(20)->through(fn (Project $project) => $project->only(['id', 'title', 'application_closes_at']));

        return Inertia::render('invitations/create', ['freelancer' => $profile->load(['user', 'skillTags'])->publicDetails(), 'projects' => $projects, 'blocked' => $blocked]);
    }

    public function store(Request $request, Profile $profile): RedirectResponse
    {
        $this->eligible($request);
        $data = $request->validate(['project_id' => ['required', 'integer']]);
        $invitation = DB::transaction(function () use ($request, $profile, $data): Invitation {
            $users = User::query()->whereIn('id', [$request->user()->id, $profile->user_id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $owner = $users->get($request->user()->id);
            $recipient = $users->get($profile->user_id);
            abort_unless($owner?->canParticipateInMarketplace() && $owner->onboarding_completed_at, 403);
            abort_unless($recipient && $recipient->id !== $owner->id && Profile::query()->publiclyVisible()->whereKey($profile->id)->exists(), 422, __('This freelancer is not available for invitations.'));
            abort_if(DemoWorld::apart($owner->id, $recipient->id), 403, __('Demo accounts and members work apart, so this is not available between them.'));
            $project = Project::query()->where('user_id', $owner->id)->whereKey($data['project_id'])->lockForUpdate()->firstOrFail();
            abort_unless(Lifecycle::open($project) && ! Lifecycle::blocked($owner->id, $recipient->id), 422, __('This invitation cannot be sent right now.'));
            abort_if(Proposal::query()->where('project_id', $project->id)->where('user_id', $recipient->id)->whereNotNull('submitted_at')->exists(), 422, __('This freelancer has already applied.'));
            $invitation = Invitation::query()->where('project_id', $project->id)->where('recipient_id', $recipient->id)->lockForUpdate()->first();
            // Resending is a separate versioned action; repeated initial requests never resend.
            abort_if($invitation !== null, 409, __('An invitation already exists. Open your sent invitations.'));
            $invitation = new Invitation;
            $invitation->forceFill(['project_id' => $project->id, 'recipient_id' => $recipient->id, 'status' => 'pending', 'version' => 1, 'sent_at' => now()])->save();
            Lifecycle::event($invitation, 'sent');
            DB::afterCommit(fn () => $recipient->notify(new WorkspaceEvent('invitation_received', '/invitations/'.$invitation->id, $project->title, $owner->name)));

            return $invitation;
        }, 3);

        return to_route('invitations.show', $invitation);
    }

    public function index(Request $request): Response
    {
        return $this->inbox($request, null);
    }

    public function show(Request $request, Invitation $invitation): Response
    {
        abort_unless($invitation->project->user_id === $request->user()->id || $invitation->recipient_id === $request->user()->id, 404);

        return $this->inbox($request, $invitation);
    }

    /**
     * Plan 032 part 14 (Ahmed's pick "B"), as Final offers: /invitations and /invitations/{id} are one inbox, the list on one
     * side and the chosen invitation on the other. Without a chosen invitation the first of the list is shown as a preview
     * on wide screens.
     */
    private function inbox(Request $request, ?Invitation $chosen): Response
    {
        $request->validate(['box' => ['nullable', 'in:received,sent'], 'page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        $user = $request->user();
        // The chosen invitation decides the box; otherwise the query, then the workspace (plan 034: only freelancers
        // receive invitations, so a client opens on the ones they sent).
        $sent = $chosen !== null ? $chosen->project->user_id === $user->id
            : ($request->query('box') ?? ($user->workspace_role === WorkspaceRole::Client ? 'sent' : 'received')) === 'sent';
        $query = $request->query('list');
        $list = is_string($query) && in_array($query, self::LISTS, true) ? $query : 'all';
        $page = null;
        $invitations = function () use (&$page, $list, $user, $sent): LengthAwarePaginator {
            // Waiting invitations first, then the newest.
            return $page ??= $this->listed($this->mine($user->id, $sent), $list)
                ->with(['project:id,user_id,title,status,application_closes_at,deleted_at', 'project.user:id,name', 'project.user.profile:id,user_id,company', 'recipient:id,name'])
                ->orderByDesc(Project::query()->withTrashed()->selectRaw('count(*)')->whereColumn('projects.id', 'invitations.project_id')
                    ->where('invitations.status', 'pending')->tap(fn (Builder $project) => Lifecycle::openProjects($project)))
                ->orderByDesc('sent_at')->orderByDesc('id')->paginate(20)->withQueryString();
        };

        return Inertia::render('invitations/index', [
            'box' => $sent ? 'sent' : 'received',
            'list' => $list,
            'invitations' => function () use ($invitations, $sent) {
                $page = $invitations();
                $facts = $this->facts($page->getCollection(), $sent);
                // through() turns the items into arrays in place, so it works on a copy: the cached page keeps its models
                // for the chosen invitation below.
                $copy = clone $page;

                return $copy->setCollection(clone $page->getCollection())->through(fn (Invitation $invitation) => $this->row($invitation, $sent, $facts));
            },
            'counts' => fn () => collect(['all', ...self::LISTS])->mapWithKeys(fn (string $name) => [$name => $this->listed($this->mine($user->id, $sent), $name)->count()])->all(),
            // The Sent / Received switch shows only for a member who has invitations in the other box.
            'other' => fn () => $this->mine($user->id, ! $sent)->count(),
            // Worked out only when asked for: a partial reload of other props (the live notifications) skips it.
            'invitation' => function () use ($chosen, $invitations, $user, $sent): ?array {
                $selected = $chosen ?? $invitations()->getCollection()->first();

                return $selected !== null ? $this->details($selected, $user, $sent) : null;
            },
            'preview' => $chosen === null,
            'readOnly' => ! $user->canParticipateInMarketplace(),
        ]);
    }

    public function update(Request $request, Invitation $invitation): RedirectResponse
    {
        $this->eligible($request);
        abort_unless($invitation->recipient_id === $request->user()->id || $invitation->project->user_id === $request->user()->id, 404);
        $data = $request->validate(['action' => ['required', 'in:decline,resend'], 'version' => ['required', 'integer', 'min:1']]);
        DB::transaction(function () use ($request, $invitation, $data): void {
            $users = User::query()->whereIn('id', [$invitation->recipient_id, $invitation->project->user_id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $actor = $users->get($request->user()->id);
            abort_unless($actor?->canParticipateInMarketplace() && $actor->onboarding_completed_at, 403);
            $project = Project::withTrashed()->lockForUpdate()->findOrFail($invitation->project_id);
            $locked = Invitation::query()->lockForUpdate()->findOrFail($invitation->id);
            abort_unless($locked->version === (int) $data['version'], 409, __('This invitation changed. Reload before responding.'));
            if ($data['action'] === 'decline') {
                abort_unless($locked->recipient_id === $actor->id, 404);
                abort_unless($locked->status === 'pending', 409);
                $locked->forceFill(['status' => 'declined', 'declined_at' => now()]);
            } else {
                abort_unless($project->user_id === $actor->id, 404);
                abort_if(DemoWorld::apart($actor->id, $locked->recipient_id), 403, __('Demo accounts and members work apart, so this is not available between them.'));
                abort_unless($locked->status === 'declined' && $locked->declined_at?->addHours(168)->lte(now()), 409, __('Wait seven days after decline before sending another invitation.'));
                abort_unless(Lifecycle::open($project) && ! Lifecycle::blocked($project->user_id, $locked->recipient_id)
                    && Profile::query()->publiclyVisible()->where('user_id', $locked->recipient_id)->exists(), 422, __('This invitation cannot be sent right now.'));
                abort_if(Proposal::query()->where('project_id', $project->id)->where('user_id', $locked->recipient_id)->whereNotNull('submitted_at')->exists(), 422, __('This freelancer has already applied.'));
                $locked->forceFill(['status' => 'pending', 'sent_at' => now()]);
            }
            $locked->version++;
            $locked->save();
            Lifecycle::event($locked, $data['action'] === 'decline' ? 'declined' : 'resent');
        }, 3);

        return back();
    }

    /** @return Builder<Invitation> */
    private function mine(int $user, bool $sent): Builder
    {
        return Invitation::query()->when($sent,
            fn (Builder $query) => $query->whereHas('project', fn (Builder $project) => $project->where('user_id', $user)),
            fn (Builder $query) => $query->where('recipient_id', $user));
    }

    /**
     * One list of the inbox. A pending invitation waits while its project takes applications; one whose project closed
     * and one cancelled by a block are closed.
     *
     * @param  Builder<Invitation>  $query
     * @return Builder<Invitation>
     */
    private function listed(Builder $query, string $list): Builder
    {
        $open = fn (Builder $project): Builder => Lifecycle::openProjects($project);

        return match ($list) {
            'waiting' => $query->where('status', 'pending')->whereHas('project', $open),
            'applied' => $query->where('status', 'accepted'),
            'declined' => $query->where('status', 'declined'),
            'closed' => $query->where(fn (Builder $query) => $query->where('status', 'cancelled')
                ->orWhere(fn (Builder $query) => $query->where('status', 'pending')->whereDoesntHave('project', $open))),
            default => $query,
        };
    }

    /**
     * What a page of rows needs beyond the invitation itself, in a few queries: which projects still take applications,
     * the freelancers' public photos (client photos are private), and when each invitation reached where it stands.
     *
     * @param  Collection<int, Invitation>  $invitations
     * @return array{open: array<int, bool>, avatars: array<int, string>, events: array<int, array<string, string>>}
     */
    private function facts(Collection $invitations, bool $sent): array
    {
        $open = Lifecycle::openProjects(Project::query())->whereIn('id', $invitations->pluck('project_id')->unique()->all())->pluck('id')->flip()->map(fn () => true)->all();
        $avatars = $sent ? Profile::query()->publiclyVisible()->whereIn('user_id', $invitations->pluck('recipient_id')->unique()->all())->whereNotNull('photo_path')->get()
            ->mapWithKeys(fn (Profile $profile) => [$profile->user_id => route('freelancers.photo', $profile)])->all() : [];
        $events = [];
        foreach (DB::table('invitation_events')->whereIn('invitation_id', $invitations->pluck('id')->all())->whereIn('kind', ['accepted', 'cancelled'])
            ->orderBy('id')->get(['invitation_id', 'kind', 'created_at']) as $event) {
            $events[$event->invitation_id][$event->kind] = CarbonImmutable::parse($event->created_at, 'UTC')->toIso8601String();
        }

        return ['open' => $open, 'avatars' => $avatars, 'events' => $events];
    }

    /**
     * Where an invitation stands for both sides, and when it got there.
     *
     * @param  array{open: array<int, bool>, avatars: array<int, string>, events: array<int, array<string, string>>}  $facts
     * @return array{0: string, 1: mixed}
     */
    private function state(Invitation $invitation, array $facts): array
    {
        $closes = $invitation->project->application_closes_at;

        return match ($invitation->status) {
            'accepted' => ['applied', $facts['events'][$invitation->id]['accepted'] ?? null],
            'declined' => ['declined', $invitation->declined_at],
            'cancelled' => ['closed', $facts['events'][$invitation->id]['cancelled'] ?? null],
            default => isset($facts['open'][$invitation->project_id]) ? ['waiting', $invitation->sent_at] : ['closed', $closes?->isPast() ? $closes : null],
        };
    }

    /**
     * The other side as the viewer met them: a client sees the freelancer's public photo, a freelancer the client's name
     * and company.
     *
     * @param  array{open: array<int, bool>, avatars: array<int, string>, events: array<int, array<string, string>>}  $facts
     * @return array{name: string, avatar: string|null, company: string|null}
     */
    private function counterpart(Invitation $invitation, bool $sent, array $facts): array
    {
        return $sent ? ['name' => $invitation->recipient->name, 'avatar' => $facts['avatars'][$invitation->recipient_id] ?? null, 'company' => null]
            : ['name' => $invitation->project->user->name, 'avatar' => null, 'company' => $invitation->project->user->profile?->company];
    }

    /**
     * @param  array{open: array<int, bool>, avatars: array<int, string>, events: array<int, array<string, string>>}  $facts
     * @return array<string, mixed>
     */
    private function row(Invitation $invitation, bool $sent, array $facts): array
    {
        [$state, $at] = $this->state($invitation, $facts);

        return [...$invitation->only(['id', 'status', 'sent_at']), 'state' => $state, 'state_at' => $at,
            'project' => $invitation->project->only(['id', 'title']), 'counterpart' => $this->counterpart($invitation, $sent, $facts)];
    }

    /** @return array<string, mixed> */
    private function details(Invitation $invitation, User $viewer, bool $sent): array
    {
        // Its own full copy: a list row carries only the columns the list shows.
        $invitation = Invitation::query()->with(['project.user.profile', 'recipient'])->findOrFail($invitation->id);
        $project = $invitation->project;
        $facts = $this->facts(collect([$invitation]), $sent);
        [$state] = $this->state($invitation, $facts);
        // A project that left public pages lends the invitation its title only.
        $public = Project::query()->visible()->whereKey($project->id)->exists();
        if ($public) {
            $project->load(['category', 'skills'])->loadCount(['proposals as proposals_received' => fn (Builder $query) => $query->whereNotNull('submitted_at')]);
        }
        $other = $sent ? $invitation->recipient_id : $project->user_id;
        $profile = $sent ? Profile::query()->publiclyVisible()->where('user_id', $other)->first() : null;
        $proposal = Proposal::query()->where('project_id', $project->id)->where('user_id', $invitation->recipient_id)->whereNotNull('submitted_at')->first(['id', 'content']);
        $available = Lifecycle::open($project) && ! Lifecycle::blocked($project->user_id, $invitation->recipient_id)
            && ! DemoWorld::apart($project->user_id, $invitation->recipient_id)
            && $invitation->recipient->canParticipateInMarketplace() && Profile::query()->publiclyVisible()->where('user_id', $invitation->recipient_id)->exists()
            && $viewer->canParticipateInMarketplace();
        $resendAt = $invitation->status === 'declined' ? $invitation->declined_at?->addHours(168) : null;

        return [
            ...$this->row($invitation, $sent, $facts), 'version' => $invitation->version, 'next_resend_at' => $resendAt?->toIso8601String(),
            'project' => $public ? ProjectDiscoveryController::summary($project) : ['id' => $project->id, 'title' => $project->title, 'open' => false],
            'public' => $public,
            'person' => [
                'name' => $sent ? $invitation->recipient->name : $project->user->name,
                'avatar' => $facts['avatars'][$other] ?? null,
                'headline' => $profile?->headline,
                'country' => $sent ? $profile?->country : $project->user->profile?->country,
                'company' => $sent ? null : $project->user->profile?->company,
                // The profile page is linked only while it is public; client profiles are not pages of their own.
                'profile' => $profile?->id,
                ...PublicReviews::record($other, $sent ? 'freelancer' : 'client'),
            ],
            'proposal' => $proposal ? ['id' => $proposal->id, 'price' => isset($proposal->content['price']) ? (string) $proposal->content['price'] : null] : null,
            // One action, only where it is the viewer's: the freelancer answers a waiting invitation, the client resends a
            // declined one once seven days have passed.
            'can_respond' => ! $sent && $state === 'waiting' && $available,
            'can_decline' => ! $sent && $state === 'waiting' && $viewer->canParticipateInMarketplace(),
            'can_resend' => $sent && $invitation->status === 'declined' && $available && $resendAt?->lte(now()) === true,
            'events' => DB::table('invitation_events')->where('invitation_id', $invitation->id)->orderBy('id')->get(['kind', 'created_at'])
                ->map(fn (object $event) => ['kind' => $event->kind, 'created_at' => CarbonImmutable::parse($event->created_at, 'UTC')->toIso8601String()]),
        ];
    }
}

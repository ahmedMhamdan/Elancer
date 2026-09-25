<?php

namespace App\Http\Controllers;

use App\Actions\Invitations\InvitationLifecycle as Lifecycle;
use App\Models\Invitation;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    private function eligible(Request $request): void
    {
        abort_unless($request->user()?->canParticipateInMarketplace() && $request->user()->onboarding_completed_at, 403);
    }

    public function create(Request $request, Profile $profile): Response
    {
        $this->eligible($request);
        abort_unless(Profile::query()->publiclyVisible()->whereKey($profile->id)->exists(), 404);
        abort_if($profile->user_id === $request->user()->id, 403);
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
            $project = Project::query()->where('user_id', $owner->id)->whereKey($data['project_id'])->lockForUpdate()->firstOrFail();
            abort_unless(Lifecycle::open($project) && ! Lifecycle::blocked($owner->id, $recipient->id), 422, __('This invitation cannot be sent right now.'));
            abort_if(Proposal::query()->where('project_id', $project->id)->where('user_id', $recipient->id)->whereNotNull('submitted_at')->exists(), 422, __('This freelancer has already applied.'));
            $invitation = Invitation::query()->where('project_id', $project->id)->where('recipient_id', $recipient->id)->lockForUpdate()->first();
            // Resending is a separate versioned action; repeated initial requests never resend.
            abort_if($invitation !== null, 409, __('An invitation already exists. Open your sent invitations.'));
            $invitation = new Invitation;
            $invitation->forceFill(['project_id' => $project->id, 'recipient_id' => $recipient->id, 'status' => 'pending', 'version' => 1, 'sent_at' => now()])->save();
            Lifecycle::event($invitation, 'sent');

            return $invitation;
        }, 3);

        return to_route('invitations.show', $invitation);
    }

    public function index(Request $request): Response
    {
        $request->validate(['box' => ['nullable', 'in:received,sent'], 'page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        $sent = $request->query('box') === 'sent';
        $query = Invitation::query()->with(['project.user', 'recipient'])->when($sent,
            fn ($q) => $q->whereHas('project', fn ($p) => $p->where('user_id', $request->user()->id)),
            fn ($q) => $q->where('recipient_id', $request->user()->id));

        return Inertia::render('invitations/index', ['box' => $sent ? 'sent' : 'received', 'invitations' => $query->orderByDesc('sent_at')->orderByDesc('id')->paginate(20)->withQueryString()->through(fn (Invitation $invitation) => $this->details($invitation))]);
    }

    public function show(Request $request, Invitation $invitation): Response
    {
        $invitation->load(['project.user', 'recipient']);
        $author = $invitation->project->user_id === $request->user()->id;
        abort_unless($author || $invitation->recipient_id === $request->user()->id, 404);
        $available = Lifecycle::open($invitation->project) && ! Lifecycle::blocked($invitation->project->user_id, $invitation->recipient_id)
            && $invitation->recipient->canParticipateInMarketplace() && Profile::query()->publiclyVisible()->where('user_id', $invitation->recipient_id)->exists();

        return Inertia::render('invitations/show', [
            'invitation' => $this->details($invitation), 'author' => $author,
            'available' => $available && $request->user()->canParticipateInMarketplace(),
            'events' => DB::table('invitation_events')->where('invitation_id', $invitation->id)->orderBy('id')->get(['kind', 'created_at']),
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

    /** @return array<string, mixed> */
    private function details(Invitation $invitation): array
    {
        return [
            ...$invitation->only(['id', 'status', 'version', 'sent_at']),
            'next_resend_at' => $invitation->status === 'declined' ? $invitation->declined_at?->addHours(168)->toIso8601String() : null,
            'project' => $invitation->project->only(['id', 'title']),
            'client_name' => $invitation->project->user->name,
            'recipient_name' => $invitation->recipient->name,
        ];
    }
}

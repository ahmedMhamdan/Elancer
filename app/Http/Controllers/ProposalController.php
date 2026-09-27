<?php

namespace App\Http\Controllers;

use App\Actions\Conversations\HiringAccess;
use App\Actions\Invitations\InvitationLifecycle;
use App\Actions\Offers\OfferLifecycle;
use App\Models\Conversation;
use App\Models\Offer;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\ProposalEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProposalController extends Controller
{
    private function eligible(Request $request): void
    {
        abort_unless($request->user()?->canParticipateInMarketplace() && $request->user()->onboarding_completed_at, 403);
    }

    private function canEdit(Project $project, ?Proposal $proposal): bool
    {
        if ($project->status !== 'published' || ! Project::query()->visible()->whereKey($project->id)->exists()) {
            return false;
        }
        if ($proposal && Offer::query()->where('proposal_id', $proposal->id)->where('status', 'pending')->where('expires_at', '>', now())->exists()) {
            return false;
        }
        if ($proposal?->status === 'declined') {
            return false;
        }

        return in_array($proposal?->status, ['submitted', 'reopened'], true) || (bool) $project->application_closes_at?->isFuture();
    }

    /** @return array<string, mixed> */
    private function projectDetails(Project $project): array
    {
        return $project->only(['id', 'title', 'budget_min', 'budget_max', 'screening_questions', 'application_closes_at']);
    }

    /** @return array<string, mixed> */
    private function details(Proposal $proposal, bool $author): array
    {
        $data = $proposal->only(['id', 'project_id', 'status', 'content', 'profile_snapshot', 'version', 'submitted_at']);
        if ($author) {
            $data['draft'] = $proposal->draft;
        } else {
            $data['organization'] = $proposal->organization;
            $data['client_note'] = $proposal->client_note;
        }
        $data['events'] = $proposal->events()->orderByDesc('id')->get(['kind', 'content', 'created_at']);

        return $data;
    }

    public function edit(Request $request, Project $project): Response
    {
        $this->eligible($request);
        abort_if($project->user_id === $request->user()->id, 403);
        $proposal = Proposal::query()->where('project_id', $project->id)->where('user_id', $request->user()->id)->first();
        abort_unless($this->canEdit($project, $proposal), 409, __('This proposal cannot be edited right now.'));

        return Inertia::render('proposals/edit', [
            'project' => $this->projectDetails($project),
            'proposal' => $proposal ? $this->details($proposal, true) : null,
            'profilePublished' => Profile::query()->publiclyVisible()->where('user_id', $request->user()->id)->exists(),
            'sampleLinks' => $request->user()->profile->professional_links ?? [],
        ]);
    }

    public function save(Request $request, Project $project): JsonResponse
    {
        $this->eligible($request);
        $request->validate(['action' => ['required', Rule::in(['save', 'submit'])], 'version' => ['required', 'integer', 'min:0']]);

        return DB::transaction(function () use ($request, $project): JsonResponse {
            $user = $request->user()->newQuery()->lockForUpdate()->findOrFail($request->user()->id);
            abort_unless($user->canParticipateInMarketplace() && $user->onboarding_completed_at, 403);
            $locked = Project::query()->lockForUpdate()->findOrFail($project->id);
            abort_if($locked->user_id === $user->id, 403);
            $proposal = Proposal::query()->where('project_id', $locked->id)->where('user_id', $user->id)->lockForUpdate()->first();
            abort_unless($this->canEdit($locked, $proposal), 409, __('This proposal cannot be edited right now.'));
            if (($proposal->version ?? 0) !== $request->integer('version')) {
                return response()->json(['conflict' => true, 'proposal' => $proposal ? $this->details($proposal, true) : null], 409);
            }
            abort_if(InvitationLifecycle::blocked($locked->user_id, $user->id), 403);
            $submit = $request->input('action') === 'submit';
            $presence = $submit ? 'required' : 'nullable';
            $data = Validator::make($request->all(), [
                'price' => [$presence, 'numeric', 'min:1', 'max:1000000', 'decimal:0,2'],
                'duration_days' => [$presence, 'integer', 'min:1', 'max:3650'],
                'message' => [$presence, 'string', 'max:10000', ...($submit ? ['min:20'] : [])],
                'answers' => ['present', 'array', 'list', 'max:3', ...($submit ? ['size:'.count($locked->screening_questions ?? [])] : [])],
                'answers.*' => [$submit ? 'required' : 'nullable', 'string', 'max:3000'],
                'samples' => ['present', 'array', 'list', 'max:3'],
                'samples.*' => ['array:label,url'],
                'samples.*.label' => ['required', 'string', 'max:80'],
                'samples.*.url' => ['required', 'url:https', 'max:2000'],
                'user_id' => ['missing'], 'status' => ['missing'], 'profile_snapshot' => ['missing'], 'client_note' => ['missing'],
            ])->validate();
            $profile = $user->profile;
            if ($submit && ! in_array($proposal?->status, ['submitted', 'reopened'], true)) {
                abort_unless($profile && Profile::query()->publiclyVisible()->whereKey($profile->id)->exists(), 422, __('Publish your freelancer profile before applying.'));
            }
            $data['price'] = isset($data['price']) ? number_format((float) $data['price'], 2, '.', '') : null;
            $data['duration_days'] = isset($data['duration_days']) ? (int) $data['duration_days'] : null;
            $data['answers'] = array_map(fn ($answer) => $answer ?? '', $data['answers']);
            $proposal ??= new Proposal;
            $previousStatus = $proposal->status;
            $proposal->forceFill(['project_id' => $locked->id, 'user_id' => $user->id, 'version' => ($proposal->exists ? $proposal->version : 0) + 1, 'draft' => $data]);
            if ($submit) {
                $first = $proposal->submitted_at === null;
                $proposal->content = $data;
                $proposal->forceFill(['price' => $data['price'], 'duration_days' => $data['duration_days']]);
                // Retain the exact shared identity; private edits never enter a recipient's view.
                if ($first) {
                    $proposal->profile_snapshot = $profile?->publicDetails();
                }
                $proposal->status = 'submitted';
                $proposal->submitted_at ??= now();
                $proposal->draft = null;
                $proposal->save();
                InvitationLifecycle::accept($proposal);
                $this->event($proposal, $user->id, $first ? 'submitted' : (in_array($previousStatus, ['withdrawn', 'reopened'], true) ? 'resubmitted' : 'revised'), $data);
            } else {
                if (! $proposal->exists) {
                    $proposal->status = 'draft';
                }
                $proposal->save();
            }

            return response()->json(['proposal' => $this->details($proposal, true), 'url' => $submit ? route('proposals.show', $proposal) : null]);
        });
    }

    public function index(Request $request): Response
    {
        $this->eligible($request);

        return Inertia::render('proposals/index', [
            'proposals' => Proposal::query()->where('user_id', $request->user()->id)->with('project')->orderByDesc('updated_at')->orderByDesc('id')->paginate(20)->through(fn (Proposal $proposal) => [
                ...$proposal->only(['id', 'status', 'submitted_at', 'updated_at']),
                'project' => $this->projectDetails($proposal->project),
                'content' => $proposal->draft ?? $proposal->content,
            ]),
        ]);
    }

    public function show(Request $request, Proposal $proposal): Response
    {
        $this->eligible($request);
        $author = $proposal->user_id === $request->user()->id;
        abort_unless($author || ($proposal->project->user_id === $request->user()->id && $proposal->submitted_at), 404);

        return Inertia::render('proposals/show', [
            'proposal' => $this->details($proposal, $author),
            'project' => $this->projectDetails($proposal->project),
            'author' => $author,
            'conversationId' => Conversation::query()->where('proposal_id', $proposal->id)->value('id'),
            'canStartConversation' => ! $author && HiringAccess::writable($proposal),
            'canOffer' => ! $author && HiringAccess::writable($proposal),
            'offers' => Offer::query()->where('proposal_id', $proposal->id)->orderByDesc('id')->get(['id', 'status']),
            'canEdit' => $author && $this->canEdit($proposal->project, $proposal),
            'canReview' => ! $author && $proposal->project->status === 'published',
        ]);
    }

    public function withdraw(Request $request, Proposal $proposal): RedirectResponse
    {
        $this->eligible($request);
        $request->validate(['version' => ['required', 'integer', 'min:1']]);
        abort_unless($proposal->user_id === $request->user()->id, 404);
        DB::transaction(function () use ($request, $proposal): void {
            $project = Project::query()->lockForUpdate()->findOrFail($proposal->project_id);
            $locked = Proposal::query()->lockForUpdate()->findOrFail($proposal->id);
            abort_unless($project->status === 'published' && $locked->version === $request->integer('version') && in_array($locked->status, ['submitted', 'reopened'], true), 409);
            OfferLifecycle::expire($project->id);
            abort_if(Offer::query()->where('proposal_id', $locked->id)->where('status', 'pending')->exists(), 409, __('Respond to the pending offer before withdrawing this proposal.'));
            $locked->status = 'withdrawn';
            $locked->version++;
            $locked->save();
            $this->event($locked, $request->user()->id, 'withdrawn');
        });

        return back()->with('success', __('Proposal withdrawn.'));
    }

    public function applicants(Request $request, Project $project): Response
    {
        $this->eligible($request);
        abort_unless($project->user_id === $request->user()->id, 404);
        $data = $request->validate(['sort' => ['nullable', Rule::in(['newest', 'price', 'duration'])], 'organization' => ['nullable', Rule::in(['received', 'shortlisted', 'archived'])]]);
        $query = $project->proposals()->whereNotNull('submitted_at');
        if (! empty($data['organization'])) {
            $query->where('organization', $data['organization']);
        }
        match ($data['sort'] ?? 'newest') {
            'price' => $query->orderBy('price'),
            'duration' => $query->orderBy('duration_days'),
            default => $query->orderByDesc('submitted_at'),
        };

        return Inertia::render('proposals/applicants', [
            'project' => $this->projectDetails($project),
            'proposals' => $query->orderByDesc('id')->paginate(20)->withQueryString()->through(fn (Proposal $proposal) => [
                ...$proposal->only(['id', 'status', 'organization', 'content', 'profile_snapshot', 'submitted_at']),
            ]),
            'filters' => ['sort' => $data['sort'] ?? 'newest', 'organization' => $data['organization'] ?? ''],
        ]);
    }

    public function review(Request $request, Proposal $proposal): RedirectResponse
    {
        $this->eligible($request);
        abort_unless($proposal->project->user_id === $request->user()->id && $proposal->submitted_at, 404);
        $data = $request->validate([
            'action' => ['required', Rule::in(['organize', 'decline', 'reopen'])],
            'organization' => ['required', Rule::in(['received', 'shortlisted', 'archived'])],
            'client_note' => ['nullable', 'string', 'max:5000'],
            'version' => ['required', 'integer', 'min:1'],
        ]);
        DB::transaction(function () use ($request, $proposal, $data): void {
            $project = Project::query()->lockForUpdate()->findOrFail($proposal->project_id);
            $locked = Proposal::query()->lockForUpdate()->findOrFail($proposal->id);
            abort_unless($project->status === 'published' && $locked->version === (int) $data['version'], 409, __('This proposal changed. Reload before reviewing it.'));
            OfferLifecycle::expire($project->id);
            if ($data['action'] === 'decline') {
                abort_if(Offer::query()->where('proposal_id', $locked->id)->where('status', 'pending')->exists(), 409, __('Withdraw the pending offer before declining this proposal.'));
                abort_unless(in_array($locked->status, ['submitted', 'reopened'], true), 409);
                $locked->status = 'declined';
                $this->event($locked, $request->user()->id, 'declined');
            } elseif ($data['action'] === 'reopen') {
                abort_unless($locked->status === 'declined', 409);
                $locked->status = 'reopened';
                $this->event($locked, $request->user()->id, 'reopened');
            }
            $locked->forceFill(['organization' => $data['organization'], 'client_note' => $data['client_note'] ?? null, 'version' => $locked->version + 1])->save();
        });

        return back()->with('success', __('Applicant review saved.'));
    }

    public function compare(Request $request, Project $project): Response
    {
        $this->eligible($request);
        abort_unless($project->user_id === $request->user()->id, 404);
        $data = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:3'], 'ids.*' => ['required', 'integer', 'distinct']]);
        $proposals = $project->proposals()->whereNotNull('submitted_at')->whereIn('id', $data['ids'])->get();
        abort_unless($proposals->count() === count($data['ids']), 404);

        return Inertia::render('proposals/compare', ['project' => $this->projectDetails($project), 'proposals' => $proposals->map(fn (Proposal $proposal) => $this->details($proposal, false))]);
    }

    /** @param array<string, mixed>|null $content */
    private function event(Proposal $proposal, int $actor, string $kind, ?array $content = null): void
    {
        $event = new ProposalEvent;
        $event->forceFill(['proposal_id' => $proposal->id, 'actor_id' => $actor, 'kind' => $kind, 'content' => $content])->save();
    }
}

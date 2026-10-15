<?php

namespace App\Http\Controllers;

use App\Actions\Conversations\HiringAccess;
use App\Actions\Demo\DemoWorld;
use App\Actions\Files\AttachedFiles;
use App\Actions\Offers\OfferLifecycle;
use App\Actions\Proposals\ProposalCases;
use App\Actions\Proposals\ProposalRules;
use App\Actions\Proposals\SaveProposal;
use App\Actions\Proposals\WithdrawProposal;
use App\Models\Contract;
use App\Models\Conversation;
use App\Models\Offer;
use App\Models\PortfolioImage;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\ProposalCase;
use App\Models\ProposalEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProposalController extends Controller
{
    private function eligible(Request $request): void
    {
        abort_unless(ProposalRules::eligible($request->user()), 403);
    }

    /** The owner reads a submitted proposal, except a member's application to a demo project, which stays with the member (plan 027). */
    private function reads(Request $request, Proposal $proposal): bool
    {
        return $proposal->project->user_id === $request->user()->id && $proposal->submitted_at !== null
            && ! DemoWorld::trial($proposal->user_id, $proposal->project->user_id);
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
        // Plan 031: the case studies each version sent, as this reader may see them now.
        $data['content'] = $proposal->content === null ? null : [...$proposal->content, 'case_samples' => ProposalCases::resolve($proposal, $proposal->content, $author)];
        if ($author) {
            // Plan 030: the files the freelancer is working with, and whether they differ from what the client received.
            $files = ProposalFileController::draft($proposal);
            $data['draft'] = $proposal->draft === null ? null : [...$proposal->draft, 'files' => $files, 'case_samples' => ProposalCases::preview($proposal, $proposal->draft)];
            $data['files'] = $files;
            $data['files_changed'] = $proposal->content !== null && array_column($files, 'id') !== array_column($proposal->content['files'] ?? [], 'id');
        } else {
            $data['organization'] = $proposal->organization;
            $data['client_note'] = $proposal->client_note;
        }
        $data['events'] = $proposal->events()->orderByDesc('id')->get(['kind', 'content', 'created_at'])->map(fn (ProposalEvent $event) => [
            'kind' => $event->kind, 'created_at' => $event->created_at,
            'content' => $event->content === null ? null : [...$event->content, 'case_samples' => ProposalCases::resolve($proposal, $event->content, $author)],
        ]);

        return $data;
    }

    public function edit(Request $request, Project $project): Response
    {
        $this->eligible($request);
        abort_if($project->user_id === $request->user()->id, 403);
        abort_if(ProposalRules::closed($request->user()->id, $project->user_id), 403, __('Demo accounts and members work apart, so this is not available between them.'));
        $proposal = Proposal::query()->where('project_id', $project->id)->where('user_id', $request->user()->id)->first();
        abort_unless(ProposalRules::editable($project, $proposal), 409, __('This proposal cannot be edited right now.'));

        return Inertia::render('proposals/edit', [
            'project' => $this->projectDetails($project),
            'proposal' => $proposal ? $this->details($proposal, true) : null,
            'profilePublished' => Profile::query()->publiclyVisible()->where('user_id', $request->user()->id)->exists(),
            'sampleLinks' => $request->user()->profile->professional_links ?? [],
            'caseOptions' => DemoWorld::trial($request->user()->id, $project->user_id) ? [] : ProposalCases::options($request->user()->id),
            'caseLimit' => ProposalCases::LIMIT,
            'fileLimit' => AttachedFiles::LIMIT,
            'trial' => DemoWorld::trial($request->user()->id, $project->user_id),
        ]);
    }

    public function save(Request $request, Project $project): JsonResponse
    {
        $result = SaveProposal::handle($request->user(), $project, $request->all());
        $proposal = $result['proposal'];
        if ($result['conflict']) {
            return response()->json(['conflict' => true, 'proposal' => $proposal ? $this->details($proposal, true) : null], 409);
        }

        return response()->json(['proposal' => $this->details($proposal, true), 'url' => $request->input('action') === 'submit' ? route('proposals.show', $proposal) : null]);
    }

    public function index(Request $request): Response
    {
        $this->eligible($request);

        return Inertia::render('proposals/index', [
            'proposals' => Proposal::query()->where('user_id', $request->user()->id)->with('project')->orderByDesc('updated_at')->orderByDesc('id')->paginate(20)->through(fn (Proposal $proposal) => [
                ...$proposal->only(['id', 'status', 'submitted_at', 'updated_at']),
                'project' => $this->projectDetails($proposal->project),
                'content' => $proposal->draft ?? $proposal->content,
                'trial' => DemoWorld::trial($proposal->user_id, $proposal->project->user_id),
            ]),
        ]);
    }

    public function show(Request $request, Proposal $proposal): Response
    {
        // Q64: a suspended member still reads the proposal behind an existing contract; nothing on it is actionable.
        if (! ($request->user()->keepsExistingAccess() && Contract::query()->where('proposal_id', $proposal->id)->exists())) {
            $this->eligible($request);
        }
        $author = $proposal->user_id === $request->user()->id;
        abort_unless($author || $this->reads($request, $proposal), 404);

        return Inertia::render('proposals/show', [
            'proposal' => $this->details($proposal, $author),
            'project' => $this->projectDetails($proposal->project),
            'author' => $author,
            'conversationId' => Conversation::query()->where('proposal_id', $proposal->id)->value('id'),
            'canStartConversation' => ! $author && HiringAccess::writable($proposal),
            'canOffer' => ! $author && HiringAccess::writable($proposal),
            'offers' => Offer::query()->where('proposal_id', $proposal->id)->orderByDesc('id')->get(['id', 'status']),
            'canEdit' => $author && ProposalRules::editable($proposal->project, $proposal),
            'canReview' => ! $author && $proposal->project->status === 'published',
            'trial' => DemoWorld::trial($proposal->user_id, $proposal->project->user_id),
        ]);
    }

    /** Plan 031: an image of a case study as this proposal sent it, to its author and its client, under Q55. */
    public function caseImage(Request $request, Proposal $proposal, ProposalCase $copy, PortfolioImage $image): StreamedResponse
    {
        if (! ($request->user()->keepsExistingAccess() && Contract::query()->where('proposal_id', $proposal->id)->exists())) {
            $this->eligible($request);
        }
        $author = $proposal->user_id === $request->user()->id;
        abort_unless($author || $this->reads($request, $proposal), 404);
        abort_unless($copy->proposal_id === $proposal->id && $image->portfolio_case_id === $copy->portfolio_case_id
            && in_array($image->id, array_column($copy->content['images'] ?? [], 'id'), true), 404);
        abort_unless($author || ProposalCases::visible($copy), 404);
        abort_unless(Storage::disk('uploads')->exists($image->path), 404);

        return Storage::disk('uploads')->response($image->path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function withdraw(Request $request, Proposal $proposal): RedirectResponse
    {
        WithdrawProposal::handle($request->user(), $proposal, $request->all());

        return back()->with('success', __('Proposal withdrawn.'));
    }

    public function applicants(Request $request, Project $project): Response
    {
        $this->eligible($request);
        abort_unless($project->user_id === $request->user()->id, 404);
        $data = $request->validate(['sort' => ['nullable', Rule::in(['newest', 'price', 'duration'])], 'organization' => ['nullable', Rule::in(['received', 'shortlisted', 'archived'])]]);
        $query = $project->proposals()->whereNotNull('submitted_at');
        DemoWorld::ownApplicants($query, $project->user_id);
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
        abort_unless($this->reads($request, $proposal), 404);
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
                ProposalRules::record($locked, $request->user()->id, 'declined');
            } elseif ($data['action'] === 'reopen') {
                abort_unless($locked->status === 'declined', 409);
                $locked->status = 'reopened';
                ProposalRules::record($locked, $request->user()->id, 'reopened');
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
        $query = $project->proposals()->whereNotNull('submitted_at')->whereIn('id', $data['ids']);
        DemoWorld::ownApplicants($query, $project->user_id);
        $proposals = $query->get();
        abort_unless($proposals->count() === count($data['ids']), 404);

        return Inertia::render('proposals/compare', ['project' => $this->projectDetails($project), 'proposals' => $proposals->map(fn (Proposal $proposal) => $this->details($proposal, false))]);
    }
}

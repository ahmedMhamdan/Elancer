<?php

namespace App\Http\Controllers;

use App\Actions\Reports\ContentModeration;
use App\Actions\Reports\Reports;
use App\Models\Contract;
use App\Models\ContractReview;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\PortfolioCase;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminReportController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['status' => ['nullable', 'in:open,resolved'], 'page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        $status = $filters['status'] ?? 'open';
        $query = $this->queue($request->user())->with(['reporter', 'subject', 'handler']);
        // The queue is worked oldest first; resolved reports read newest first.
        $status === 'open' ? $query->whereIn('status', ['submitted', 'in_review'])->orderBy('id') : $query->where('status', 'resolved')->orderByDesc('id');

        return Inertia::render('admin/reports/index', [
            'reports' => $query->paginate(10)->withQueryString()->through(fn (Report $report) => [
                ...$report->only(['id', 'target_type', 'reason', 'status', 'outcome', 'created_at']),
                'title' => $report->snapshot['title'] ?? '',
                'reporter' => $report->reporter?->name, 'subject' => $report->subject?->name, 'handler' => $report->handler?->name,
            ]),
            'status' => $status,
        ]);
    }

    public function show(Request $request, Report $report): Response
    {
        $actor = $request->user();
        abort_unless(Reports::handles($actor, $report), 404);
        $report->load(['reporter', 'subject', 'handler']);
        $people = User::query()->whereIn('id', DB::table('report_notes')->where('report_id', $report->id)->pluck('author_id')
            ->merge(DB::table('moderation_events')->where('report_id', $report->id)->pluck('actor_id'))->filter()->unique())->pluck('name', 'id');
        $mine = $report->status === 'in_review' && $report->handler_id === $actor->id;
        $content = ContentModeration::target($report);

        return Inertia::render('admin/reports/show', [
            'report' => [
                ...$report->only(['id', 'target_type', 'target_id', 'reason', 'explanation', 'status', 'outcome', 'snapshot', 'created_at', 'resolved_at']),
                'reporter' => $report->reporter?->only(['name', 'email']),
                'subject' => $report->subject ? [...$report->subject->only(['id', 'name', 'email']), 'status' => $report->subject->status->value] : null,
                'handler' => $report->handler?->name,
                // Other reports about the same member, so a pattern is visible without browsing accounts.
                'subject_reports' => $report->subject_id === null ? 0 : $this->queue($actor)->where('subject_id', $report->subject_id)->whereKeyNot($report->id)->count(),
            ],
            'target' => $this->target($report),
            // Q68: whether the reported message, project or case study is hidden right now, and by whom.
            'moderation' => $content === null ? null : [
                'hidden' => $content->moderated_at !== null, 'at' => $content->moderated_at,
                'by' => $content->moderated_by === null ? null : User::query()->whereKey($content->moderated_by)->value('name'),
            ],
            'notes' => DB::table('report_notes')->where('report_id', $report->id)->orderBy('id')->get()
                ->map(fn ($note) => ['id' => $note->id, 'body' => $note->body, 'author' => $people->get($note->author_id), 'created_at' => $this->iso($note->created_at)]),
            'events' => DB::table('moderation_events')->where('report_id', $report->id)->orderByDesc('id')->limit(50)->get()
                ->map(fn ($event) => ['id' => $event->id, 'action' => $event->action, 'actor' => $people->get($event->actor_id), 'reason' => $event->reason, 'created_at' => $this->iso($event->created_at)]),
            'can' => [
                'start' => $report->status === 'submitted',
                'take' => $report->status === 'in_review' && ! $mine,
                'resolve' => $mine,
                'conversation' => $mine && $report->conversation_id !== null,
                'hide' => $content !== null && $content->moderated_at === null && ContentModeration::canHide($actor, $report),
                'restore' => $content !== null && $content->moderated_at !== null && ContentModeration::canRestore($actor, $report),
            ],
            'notice' => $request->session()->get('report_notice'),
        ]);
    }

    public function update(Request $request, Report $report): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['start', 'resolve', 'hide', 'restore'])],
            'outcome' => ['required_if:action,resolve', 'nullable', Rule::in(Report::OUTCOMES)],
            'reason' => ['required_if:action,resolve,hide,restore', 'nullable', 'string', 'min:5', 'max:1000'],
        ]);
        match ($data['action']) {
            'start' => Reports::start($request->user(), $report),
            'resolve' => Reports::resolve($request->user(), $report, (string) $data['outcome'], (string) $data['reason']),
            'hide' => ContentModeration::hide($request->user(), $report, (string) $data['reason']),
            default => ContentModeration::restore($request->user(), $report, (string) $data['reason']),
        };

        return to_route('admin.reports.show', $report)->with('report_notice', ['start' => 'started', 'resolve' => 'resolved', 'hide' => 'hidden', 'restore' => 'restored'][$data['action']]);
    }

    public function note(Request $request, Report $report): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:2000']]);
        Reports::note($request->user(), $report, $data['body']);

        return to_route('admin.reports.show', $report)->with('report_notice', 'noted');
    }

    /** Q65: the full reported conversation, and nothing else from either inbox. Every load is one audit row. */
    public function conversation(Request $request, Report $report): Response
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        Reports::readConversation($request->user(), $report);
        $conversation = Conversation::query()->with(['client', 'freelancer', 'proposal.project'])->findOrFail($report->conversation_id);
        $messages = $conversation->messages()->orderBy('id')->paginate(50)->withQueryString();
        $revisions = $this->revisions($messages->getCollection()->map(fn (ConversationMessage $message) => $message->id)->values()->all());

        return Inertia::render('admin/reports/conversation', [
            'report' => ['id' => $report->id, 'reported_message' => $report->target_type === 'message' ? $report->target_id : null],
            'conversation' => ['project' => $conversation->proposal->project->title, 'client' => $conversation->client->name, 'freelancer' => $conversation->freelancer->name],
            'messages' => $messages->through(fn (ConversationMessage $message) => [
                ...$message->only(['id', 'body', 'created_at', 'edited_at']),
                'hidden' => $message->moderated_at !== null,
                'sender' => $message->sender_id === $conversation->client_id ? 'client' : 'freelancer',
                'revisions' => $revisions[$message->id] ?? [],
            ]),
        ]);
    }

    /**
     * Reports this administrator may work on: never one they filed or one about themselves.
     *
     * @return Builder<Report>
     */
    private function queue(User $actor): Builder
    {
        return Report::query()
            ->where(fn (Builder $q) => $q->whereNull('reporter_id')->orWhere('reporter_id', '!=', $actor->id))
            ->where(fn (Builder $q) => $q->whereNull('subject_id')->orWhere('subject_id', '!=', $actor->id));
    }

    /**
     * What the reported thing is now. No contract files, payment details or identity documents.
     *
     * @return array<string, mixed>|null
     */
    private function target(Report $report): ?array
    {
        $id = $report->target_id;
        if ($report->target_type === 'project') {
            $project = Project::query()->withTrashed()->find($id);

            return $project ? ['title' => $project->title, 'text' => $project->description,
                'href' => Project::query()->visible()->whereKey($id)->exists() ? '/jobs/'.$id : null] : null;
        }
        if ($report->target_type === 'profile') {
            $profile = Profile::query()->with('user')->find($id);

            return $profile ? ['title' => $profile->user->name, 'summary' => $profile->headline, 'text' => $profile->bio,
                'href' => Profile::query()->publiclyVisible()->whereKey($id)->exists() ? '/freelancers/'.$id : null] : null;
        }
        if ($report->target_type === 'case') {
            // Only ever the public version: the private working copy is not part of what was reported.
            // A case hidden by moderation still shows that version here, so a restore is an informed one.
            $case = PortfolioCase::query()->whereKey($id)->whereNotNull('public_content')->first();
            $public = PortfolioCase::query()->publiclyVisible()->whereKey($id)->exists();

            return $case && ($public || $case->moderated_at !== null) ? ['title' => $case->public_content['title'] ?? '', 'summary' => $case->public_content['summary'] ?? '',
                'text' => $case->public_content['body'] ?? '', 'href' => $public ? '/portfolio/'.$id : null] : null;
        }
        if ($report->target_type === 'message') {
            $message = ConversationMessage::query()->with('conversation')->find($id);

            return $message ? ['text' => $message->body, 'hidden' => $message->moderated_at !== null, 'created_at' => $message->created_at, 'edited_at' => $message->edited_at,
                'sender' => $message->sender_id === $message->conversation->client_id ? 'client' : 'freelancer',
                'revisions' => $this->revisions([$message->id])[$message->id] ?? []] : null;
        }
        $contract = Contract::query()->find($id);
        if (! $contract) {
            return null;
        }
        $names = User::query()->whereIn('id', [$contract->client_id, $contract->freelancer_id])->pluck('name', 'id');
        $reviews = ContractReview::query()->where('contract_id', $contract->id)->orderBy('id')->get();
        $history = DB::table('contract_review_revisions')->whereIn('contract_review_id', $reviews->pluck('id'))->orderBy('id')->get()->groupBy('contract_review_id');

        return [
            'title' => $contract->agreement['project_title'] ?? '', 'status' => $contract->status,
            'client' => $names->get($contract->client_id), 'freelancer' => $names->get($contract->freelancer_id),
            'scope' => $contract->agreement['scope'] ?? '', 'amount' => $contract->agreement['amount'] ?? null,
            'created_at' => $contract->getAttribute('created_at'), 'funded_at' => $contract->funded_at,
            'completed_at' => $contract->completed_at, 'cancelled_at' => $contract->cancelled_at,
            'reviews' => $reviews->map(fn (ContractReview $review) => [
                'id' => $review->id, 'author' => $review->author_id === $contract->client_id ? 'client' : 'freelancer',
                'rating' => $review->rating, 'body' => $review->body,
                'revisions' => ($history->get($review->id) ?? collect())->map(fn ($revision) => ['rating' => $revision->rating, 'body' => $revision->body, 'created_at' => $this->iso($revision->created_at)])->values(),
            ]),
        ];
    }

    /**
     * Correction history per message, oldest first.
     *
     * @param  array<int, int>  $messages  message ids; the keys are not used
     * @return array<int, list<array{body: string, version: int, created_at: string}>>
     */
    private function revisions(array $messages): array
    {
        $grouped = [];
        foreach (DB::table('message_revisions')->whereIn('conversation_message_id', $messages)->orderBy('version')->get() as $revision) {
            $grouped[$revision->conversation_message_id][] = ['body' => $revision->body, 'version' => (int) $revision->version, 'created_at' => $this->iso($revision->created_at)];
        }

        return $grouped;
    }

    /** Query-builder rows carry plain database times; pages expect the format models send. */
    private function iso(mixed $time): string
    {
        return Carbon::parse((string) $time)->toIso8601String();
    }
}

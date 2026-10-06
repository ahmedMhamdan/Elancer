<?php

namespace App\Actions\Reports;

use App\Models\Contract;
use App\Models\ConversationMessage;
use App\Models\PortfolioCase;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Reports
{
    /**
     * Q38: a member reports only what they can already see; anything else is not found. Filing a
     * report never changes the reported thing.
     */
    public static function file(User $reporter, string $type, int $id, string $reason, string $explanation): Report
    {
        return DB::transaction(function () use ($reporter, $type, $id, $reason, $explanation): Report {
            // Serialises this member's reports, so the open-report check cannot race.
            User::query()->whereKey($reporter->id)->lockForUpdate()->firstOrFail();
            $target = self::target($reporter->id, $type, $id);
            abort_if($target === null, 404);
            if ($target['subject_id'] === $reporter->id) {
                throw ValidationException::withMessages(['report' => __('You cannot report your own content.')]);
            }
            $key = $reporter->id.':'.$type.':'.$id;
            if (Report::query()->where('open_key', $key)->exists()) {
                throw ValidationException::withMessages(['report' => __('You already have an open report about this. Follow it under My reports.')]);
            }
            $report = new Report;
            $report->forceFill([...$target, 'reporter_id' => $reporter->id, 'target_type' => $type, 'target_id' => $id,
                'reason' => $reason, 'explanation' => $explanation, 'status' => 'submitted', 'open_key' => $key])->save();

            return $report;
        }, 3);
    }

    /** Whether this administrator may work on the report: never one they filed or one about themselves. */
    public static function handles(User $actor, Report $report): bool
    {
        return $actor->isAdministrator() && $actor->canParticipateInMarketplace()
            && $report->reporter_id !== $actor->id && $report->subject_id !== $actor->id;
    }

    /** Takes the report into review, or takes it over from another reviewer. One reviewer at a time. */
    public static function start(User $actor, Report $report): void
    {
        DB::transaction(function () use ($actor, $report): void {
            $locked = Report::query()->lockForUpdate()->findOrFail($report->id);
            abort_unless(self::handles($actor, $locked), 404);
            abort_unless($locked->status === 'submitted' || ($locked->status === 'in_review' && $locked->handler_id !== $actor->id), 409, __('This report changed. Reload before continuing.'));
            $action = $locked->status === 'submitted' ? 'review_started' : 'review_taken_over';
            $locked->forceFill(['status' => 'in_review', 'handler_id' => $actor->id])->save();
            self::audit($actor, $action, $locked);
        }, 3);
    }

    /** Q28: resolving records an outcome only. It never completes, cancels or refunds anything. */
    public static function resolve(User $actor, Report $report, string $outcome, string $reason): void
    {
        DB::transaction(function () use ($actor, $report, $outcome, $reason): void {
            $locked = Report::query()->lockForUpdate()->findOrFail($report->id);
            abort_unless(self::handles($actor, $locked), 404);
            abort_unless($locked->status === 'in_review' && $locked->handler_id === $actor->id, 409, __('This report changed. Reload before continuing.'));
            $locked->forceFill(['status' => 'resolved', 'outcome' => $outcome, 'resolved_at' => now(), 'open_key' => null])->save();
            self::audit($actor, 'resolved', $locked, $reason);
        }, 3);
    }

    public static function note(User $actor, Report $report, string $body): void
    {
        DB::transaction(function () use ($actor, $report, $body): void {
            $locked = Report::query()->lockForUpdate()->findOrFail($report->id);
            abort_unless(self::handles($actor, $locked), 404);
            DB::table('report_notes')->insert(['report_id' => $locked->id, 'author_id' => $actor->id, 'body' => $body, 'created_at' => now()]);
            // The note text stays in report_notes; the log records only that one was written.
            self::audit($actor, 'note_added', $locked);
        }, 3);
    }

    /** Q65: the reviewer handling a report may read its conversation, and every read is logged. */
    public static function readConversation(User $actor, Report $report): void
    {
        abort_unless(self::handles($actor, $report), 404);
        abort_unless($report->conversation_id !== null && $report->status === 'in_review' && $report->handler_id === $actor->id, 403);
        self::audit($actor, 'conversation_read', $report);
    }

    public static function audit(User $actor, string $action, Report $report, ?string $reason = null): void
    {
        DB::table('moderation_events')->insert(['actor_id' => $actor->id, 'actor_email' => $actor->email, 'action' => $action,
            'report_id' => $report->id, 'target_type' => $report->target_type, 'target_id' => $report->target_id,
            'conversation_id' => $action === 'conversation_read' ? $report->conversation_id : null,
            'subject_id' => $report->subject_id, 'reason' => $reason, 'created_at' => now()]);
    }

    /**
     * The reported thing as this member can see it, or null when they cannot.
     *
     * @return array{subject_id: int, conversation_id: int|null, snapshot: array<string, mixed>}|null
     */
    private static function target(int $user, string $type, int $id): ?array
    {
        if ($type === 'project') {
            $project = Project::query()->visible()->whereKey($id)->first();

            return $project ? ['subject_id' => $project->user_id, 'conversation_id' => null, 'snapshot' => ['title' => $project->title, 'text' => $project->description]] : null;
        }
        if ($type === 'profile') {
            $profile = Profile::query()->publiclyVisible()->with('user')->whereKey($id)->first();

            return $profile ? ['subject_id' => $profile->user_id, 'conversation_id' => null, 'snapshot' => ['title' => $profile->user->name, 'summary' => $profile->headline, 'text' => $profile->bio]] : null;
        }
        if ($type === 'case') {
            $case = PortfolioCase::query()->publiclyVisible()->whereKey($id)->first();
            if (! $case) {
                return null;
            }
            $content = $case->public_content ?? [];

            return ['subject_id' => $case->user_id, 'conversation_id' => null, 'snapshot' => ['title' => $content['title'] ?? '', 'summary' => $content['summary'] ?? '', 'text' => $content['body'] ?? '']];
        }
        if ($type === 'message') {
            $message = ConversationMessage::query()->with('conversation.proposal.project')->whereKey($id)->first();
            if (! $message || ! $message->conversation->contains($user)) {
                return null;
            }

            // The message row and its correction history are the evidence; no text is copied here.
            return ['subject_id' => $message->sender_id, 'conversation_id' => $message->conversation_id, 'snapshot' => ['title' => $message->conversation->proposal->project->title]];
        }
        $contract = $type === 'contract' ? Contract::query()->whereKey($id)->first() : null;
        if (! $contract || ! $contract->contains($user)) {
            return null;
        }

        return ['subject_id' => $user === $contract->client_id ? $contract->freelancer_id : $contract->client_id,
            'conversation_id' => $contract->conversation_id, 'snapshot' => ['title' => $contract->agreement['project_title'] ?? '']];
    }
}

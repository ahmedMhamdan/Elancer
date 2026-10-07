<?php

namespace App\Actions\Reports;

use App\Events\WorkspaceSignal;
use App\Models\ConversationMessage;
use App\Models\PortfolioCase;
use App\Models\Project;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Q68, Q84: an administrator hides and restores reported content from its report. The row is
 * kept exactly as it was; only moderated_at and moderated_by change. Nothing here completes,
 * cancels or refunds a contract (Q28).
 */
class ContentModeration
{
    /** Report targets that can be hidden. Profiles and contracts cannot. */
    public const TARGETS = ['message', 'project', 'case'];

    /** Hiding belongs to the administrator whose review is in progress. */
    public static function canHide(User $actor, Report $report): bool
    {
        return Reports::handles($actor, $report) && in_array($report->target_type, self::TARGETS, true)
            && $report->status === 'in_review' && $report->handler_id === $actor->id;
    }

    /** Restoring is also open once the report is resolved, so a mistake can be undone later. */
    public static function canRestore(User $actor, Report $report): bool
    {
        return self::canHide($actor, $report)
            || (Reports::handles($actor, $report) && in_array($report->target_type, self::TARGETS, true) && $report->status === 'resolved');
    }

    public static function hide(User $actor, Report $report, string $reason): void
    {
        self::change($actor, $report, $reason, true);
    }

    /** Restoring returns the content as it was. It does not reopen a message's correction window. */
    public static function restore(User $actor, Report $report, string $reason): void
    {
        self::change($actor, $report, $reason, false);
    }

    /** The reported thing, when it still exists and can be hidden. */
    public static function target(Report $report, bool $lock = false): ConversationMessage|Project|PortfolioCase|null
    {
        $id = $report->target_id;

        return match ($report->target_type) {
            'message' => ConversationMessage::query()->when($lock, fn ($q) => $q->lockForUpdate())->whereKey($id)->first(),
            'project' => Project::query()->when($lock, fn ($q) => $q->lockForUpdate())->whereKey($id)->first(),
            'case' => PortfolioCase::query()->when($lock, fn ($q) => $q->lockForUpdate())->whereKey($id)->first(),
            default => null,
        };
    }

    private static function change(User $actor, Report $report, string $reason, bool $hide): void
    {
        DB::transaction(function () use ($actor, $report, $reason, $hide): void {
            $locked = Report::query()->lockForUpdate()->findOrFail($report->id);
            abort_unless(Reports::handles($actor, $locked) && in_array($locked->target_type, self::TARGETS, true), 404);
            abort_unless($hide ? self::canHide($actor, $locked) : self::canRestore($actor, $locked), 409, __('This report changed. Reload before continuing.'));
            $target = self::target($locked, true);
            abort_unless($target !== null && ($target->moderated_at === null) === $hide, 409, __('This report changed. Reload before continuing.'));
            // The row's own timestamps stay as they were: hiding is not an edit.
            $target->timestamps = false;
            $target->forceFill(['moderated_at' => $hide ? now() : null, 'moderated_by' => $hide ? $actor->id : null])->save();
            Reports::audit($actor, $hide ? 'content_hidden' : 'content_restored', $locked, $reason);
            if ($target instanceof ConversationMessage) {
                $conversation = $target->conversation;
                // Open pages of both participants reload; the signal carries identifiers only.
                DB::afterCommit(function () use ($conversation): void {
                    WorkspaceSignal::send($conversation->client_id, conversation: $conversation->id);
                    WorkspaceSignal::send($conversation->freelancer_id, conversation: $conversation->id);
                });
            }
        }, 3);
    }
}

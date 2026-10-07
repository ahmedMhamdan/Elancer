<?php

namespace App\Actions\Accounts;

use App\Actions\Reports\Reports;
use App\Enums\AccountStatus;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AccountModeration
{
    /** Q69: ordinary members only. Never an administrator, a super administrator or the actor's own account. */
    public static function manages(User $actor, User $target): bool
    {
        return $actor->isAdministrator() && $actor->canParticipateInMarketplace()
            && $actor->id !== $target->id && ! $target->isAdministrator();
    }

    /**
     * Q64, Q70: the member keeps existing work; saving the status closes their pending offers.
     * Nothing here completes, cancels or refunds a contract (Q28).
     */
    public static function suspend(User $actor, User $target, string $reason, string $memberReason, ?Report $report = null): void
    {
        DB::transaction(function () use ($actor, $target, $reason, $memberReason, $report): void {
            $locked = User::query()->lockForUpdate()->findOrFail($target->id);
            self::authorize($actor, $locked, $report);
            abort_unless($locked->status === AccountStatus::Active, 409, __('This account changed. Reload before continuing.'));
            $locked->forceFill(['status' => AccountStatus::Suspended, 'suspension_reason' => $memberReason, 'suspended_at' => now()])->save();
            self::audit($actor, 'account_suspended', $locked, $reason, $memberReason, $report);
        }, 3);
    }

    /** Reinstating never revives the offers that suspension closed. */
    public static function reinstate(User $actor, User $target, string $reason, ?Report $report = null): void
    {
        DB::transaction(function () use ($actor, $target, $reason, $report): void {
            $locked = User::query()->lockForUpdate()->findOrFail($target->id);
            self::authorize($actor, $locked, $report);
            abort_unless($locked->status === AccountStatus::Suspended, 409, __('This account changed. Reload before continuing.'));
            $locked->forceFill(['status' => AccountStatus::Active, 'suspension_reason' => null, 'suspended_at' => null])->save();
            self::audit($actor, 'account_reinstated', $locked, $reason, null, $report);
        }, 3);
    }

    /** A report is named only when it is about this member and this administrator may work on it. */
    public static function linked(User $actor, User $target, ?Report $report): bool
    {
        return $report !== null && $report->subject_id === $target->id && Reports::handles($actor, $report);
    }

    private static function authorize(User $actor, User $target, ?Report $report): void
    {
        abort_unless(self::manages($actor, $target), 403);
        abort_unless($report === null || self::linked($actor, $target, $report), 404);
    }

    private static function audit(User $actor, string $action, User $target, string $reason, ?string $memberReason, ?Report $report): void
    {
        DB::table('moderation_events')->insert(['actor_id' => $actor->id, 'actor_email' => $actor->email, 'action' => $action,
            'report_id' => $report?->id, 'target_type' => 'account', 'target_id' => $target->id, 'subject_id' => $target->id,
            'reason' => $reason, 'member_reason' => $memberReason, 'created_at' => now()]);
    }
}

<?php

namespace App\Actions\Contracts;

use App\Models\Contract;
use App\Models\ContractAmendment;
use App\Models\User;
use App\Notifications\WorkspaceEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Q21, Q50, Q81: a new date or extra revision rounds bind only when the counterpart accepts.
 * The price and the accepted agreement never change.
 */
class ContractAmendments
{
    /** Q87: a pending cancellation pauses amendments, so only working states qualify. */
    private const WORKING = ['active', 'submitted', 'revision_requested'];

    /** Included and extra rounds together, matching the limit of a final offer. */
    public const MAX_ROUNDS = 20;

    /** @param  array<string, mixed>  $data */
    public static function propose(Contract $contract, int $user, array $data): void
    {
        DB::transaction(function () use ($contract, $user, $data): void {
            $locked = Contract::query()->lockForUpdate()->findOrFail($contract->id);
            if (! in_array($locked->status, self::WORKING, true)) {
                throw ValidationException::withMessages(['amendment' => __('This contract cannot be changed right now. Reload to see its current status.')]);
            }
            if (ContractAmendment::query()->where('open_contract_id', $locked->id)->exists()) {
                throw ValidationException::withMessages(['amendment' => __('Another proposed change is still awaiting an answer.')]);
            }
            $due = isset($data['due_at']) ? CarbonImmutable::parse($data['due_at']) : null;
            $kind = $due === null ? null : self::dateKind($locked);
            if ($due !== null && $kind === null) {
                throw ValidationException::withMessages(['due_at' => __('A new date can be proposed only while a delivery or a revision is being worked on.')]);
            }
            $rounds = (int) ($data['extra_rounds'] ?? 0);
            if ((int) $locked->agreement['revision_rounds'] + $locked->extra_rounds + $rounds > self::MAX_ROUNDS) {
                throw ValidationException::withMessages(['extra_rounds' => __('A contract can have at most :count revision rounds.', ['count' => self::MAX_ROUNDS])]);
            }
            $amendment = new ContractAmendment;
            $amendment->forceFill(['contract_id' => $locked->id, 'open_contract_id' => $locked->id, 'proposer_id' => $user, 'reason' => $data['reason'],
                'date_kind' => $kind, 'new_due_at' => $due, 'previous_due_at' => $kind === 'revision' ? $locked->revision_due_at : ($kind ? $locked->delivery_due_at : null),
                'extra_rounds' => $rounds, 'revisions_used' => $locked->revisions_used, 'status' => 'pending'])->save();
            self::notify($locked, 'amendment_proposed', self::other($locked, $user), $user);
        }, 3);
    }

    /** The answer names the amendment it was shown, so a stale page cannot accept a different one. */
    public static function respond(Contract $contract, int $user, int $amendment, string $action): void
    {
        DB::transaction(function () use ($contract, $user, $amendment, $action): void {
            $locked = Contract::query()->lockForUpdate()->findOrFail($contract->id);
            $pending = ContractAmendment::query()->where('open_contract_id', $locked->id)->lockForUpdate()->first();
            if (! $pending || $pending->id !== $amendment || ! in_array($locked->status, self::WORKING, true)) {
                throw ValidationException::withMessages(['amendment' => __('This proposed change is no longer awaiting an answer. Reload to see the current status.')]);
            }
            abort_unless(($action === 'withdraw') === ($pending->proposer_id === $user), 403);
            if ($action === 'accept') {
                // A date belongs to one step of the work; once that step has passed it cannot bind.
                if ($pending->date_kind !== null && ($pending->date_kind !== self::dateKind($locked) || $pending->revisions_used !== $locked->revisions_used)) {
                    throw ValidationException::withMessages(['amendment' => __('The work has moved on since this date was proposed. Decline it so a new change can be proposed.')]);
                }
                $locked->forceFill(['extra_rounds' => $locked->extra_rounds + $pending->extra_rounds,
                    ...($pending->date_kind === null ? [] : [$pending->date_kind === 'revision' ? 'revision_due_at' : 'delivery_due_at' => $pending->new_due_at])])->save();
            }
            $pending->forceFill(['status' => ['accept' => 'accepted', 'decline' => 'declined', 'withdraw' => 'withdrawn'][$action], 'decided_at' => now(), 'open_contract_id' => null])->save();
            self::notify($locked, 'amendment_'.$pending->status, self::other($locked, $user), $user);
        }, 3);
    }

    /** Called inside the transaction that completes or cancels the contract: nothing stays pending on finished work. */
    public static function close(Contract $contract): void
    {
        ContractAmendment::query()->where('open_contract_id', $contract->id)->update(['status' => 'closed', 'decided_at' => now(), 'open_contract_id' => null]);
    }

    /** Q80, Q81: the first delivery date and a revision date are separate. */
    private static function dateKind(Contract $contract): ?string
    {
        return ['active' => 'delivery', 'revision_requested' => 'revision'][$contract->status] ?? null;
    }

    private static function other(Contract $contract, int $user): int
    {
        return $user === $contract->client_id ? $contract->freelancer_id : $contract->client_id;
    }

    private static function notify(Contract $contract, string $kind, int $recipient, int $actor): void
    {
        $title = $contract->agreement['project_title'] ?? null;
        $name = $actor === $contract->client_id ? $contract->agreement['client_name'] ?? null : $contract->agreement['freelancer_name'] ?? null;
        DB::afterCommit(fn () => User::query()->find($recipient)?->notify(new WorkspaceEvent($kind, '/contracts/'.$contract->id, $title, $name)));
    }
}

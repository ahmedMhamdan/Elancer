<?php

namespace App\Actions\Contracts;

use App\Models\Contract;
use App\Models\ContractCancellation;
use App\Models\PaymentAttempt;
use App\Models\PaymentEvent;
use App\Models\User;
use App\Notifications\WorkspaceEvent;
use App\Payments\Gateways;
use App\Payments\Refund;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelContract
{
    /** Funded states a cancellation request can pause and later restore. */
    private const FUNDED = ['active', 'submitted', 'revision_requested'];

    public function __construct(private Gateways $gateways) {}

    /** An unfunded contract ends at once; a funded one needs the counterpart's agreement and a verified refund. */
    public function request(Contract $contract, int $user, string $reason): void
    {
        DB::transaction(function () use ($contract, $user, $reason): void {
            $locked = Contract::query()->lockForUpdate()->findOrFail($contract->id);
            $funded = in_array($locked->status, self::FUNDED, true);
            if (! $funded && $locked->status !== 'awaiting_payment') {
                throw ValidationException::withMessages(['cancellation' => __('This contract can no longer be cancelled. Reload to see its current status.')]);
            }
            // A checkout still underway could capture after the contract closed, so it is settled first.
            if (! $funded && PaymentAttempt::query()->where('open_contract_id', $locked->id)->exists()) {
                throw ValidationException::withMessages(['cancellation' => __('Finish or cancel the open payment attempt before cancelling this contract.')]);
            }
            $request = new ContractCancellation;
            $request->forceFill(['contract_id' => $locked->id, 'open_contract_id' => $funded ? $locked->id : null, 'requester_id' => $user, 'reason' => $reason,
                'prior_status' => $locked->status, 'status' => $funded ? 'pending' : 'cancelled', 'decided_at' => $funded ? null : now()])->save();
            $locked->forceFill($funded ? ['status' => 'cancellation_pending'] : ['status' => 'cancelled', 'cancelled_at' => now()])->save();
            self::notify($locked, $funded ? 'cancellation_requested' : 'contract_cancelled', $this->other($locked, $user), $user);
        }, 3);
    }

    /** Q87: decline or withdrawal restores the earlier state; acceptance waits for the refund. */
    public function respond(Contract $contract, int $user, string $action): void
    {
        $accepted = DB::transaction(function () use ($contract, $user, $action): bool {
            $locked = Contract::query()->lockForUpdate()->findOrFail($contract->id);
            $request = ContractCancellation::query()->where('open_contract_id', $locked->id)->lockForUpdate()->first();
            if (! $request || $request->status !== 'pending') {
                throw ValidationException::withMessages(['cancellation' => __('This cancellation request is no longer awaiting a response. Reload to see its current status.')]);
            }
            abort_unless(($action === 'withdraw') === ($request->requester_id === $user), 403);
            if ($action === 'accept') {
                $request->forceFill(['status' => 'accepted', 'decided_at' => now(), 'refund_status' => 'pending'])->save();
                self::notify($locked, 'cancellation_accepted', $request->requester_id, $user);

                return true;
            }
            $request->forceFill(['status' => $action === 'withdraw' ? 'withdrawn' : 'declined', 'decided_at' => now(), 'open_contract_id' => null])->save();
            $locked->forceFill(['status' => $request->prior_status])->save();
            self::notify($locked, $action === 'withdraw' ? 'cancellation_withdrawn' : 'cancellation_declined', $this->other($locked, $user), $user);

            return false;
        }, 3);
        if ($accepted) {
            $this->refund($contract);
        }
    }

    /**
     * Ask the provider outside any lock, then apply the answer once. A failed refund never
     * resumes work or completes the cancellation: it stays visible with a retry.
     */
    public function refund(Contract $contract): void
    {
        $request = ContractCancellation::query()->where('open_contract_id', $contract->id)->where('status', 'accepted')->first();
        if (! $request) {
            return;
        }
        $attempt = PaymentAttempt::query()->where('contract_id', $contract->id)->where('status', 'succeeded')->latest('id')->first();
        $capture = $attempt ? PaymentEvent::query()->where('payment_attempt_id', $attempt->id)->where('type', 'capture')->value('event_reference') : null;
        if (! $attempt || ! is_string($capture)) {
            $result = Refund::failed('unknown_payment');
        } else {
            try {
                // After a failure the provider is asked afresh rather than about the failed refund.
                $failed = $request->refund_status === 'failed';
                $result = $this->gateways->for($attempt->provider)->refund($attempt, $capture, $failed ? null : $request->refund_reference, $failed ? $request->refund_reference : null);
            } catch (\Throwable $exception) {
                report($exception);
                $result = Refund::failed('provider_unavailable', $request->refund_reference);
            }
        }
        DB::transaction(function () use ($contract, $request, $attempt, $result): void {
            $locked = Contract::query()->lockForUpdate()->findOrFail($contract->id);
            $current = ContractCancellation::query()->lockForUpdate()->findOrFail($request->id);
            if ($current->status !== 'accepted') {
                return;
            }
            if ($result->state === Refund::SUCCEEDED && $attempt && ($result->amountMinor !== $attempt->amount_minor || $result->currency !== $attempt->currency)) {
                $current->forceFill(['refund_status' => 'failed', 'refund_failure' => 'mismatch', 'refund_reference' => $result->reference])->save();

                return;
            }
            if ($result->state !== Refund::SUCCEEDED || ! $attempt) {
                $current->forceFill(['refund_status' => $result->state === Refund::PENDING ? 'pending' : 'failed',
                    'refund_failure' => $result->reason, 'refund_reference' => $result->reference ?? $current->refund_reference])->save();

                return;
            }
            if (! PaymentEvent::query()->where('provider', $attempt->provider)->where('environment', $attempt->environment)->where('event_reference', 'refund:'.$result->reference)->exists()) {
                $event = new PaymentEvent;
                $event->forceFill(['payment_attempt_id' => $attempt->id, 'provider' => $attempt->provider, 'environment' => $attempt->environment,
                    'event_reference' => 'refund:'.$result->reference, 'type' => 'refund', 'amount_minor' => $result->amountMinor, 'currency' => $result->currency])->save();
            }
            PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id)->forceFill(['status' => 'refunded'])->save();
            $current->forceFill(['status' => 'refunded', 'refund_status' => 'succeeded', 'refund_failure' => null, 'refund_reference' => $result->reference,
                'refunded_at' => now(), 'open_contract_id' => null])->save();
            $locked->forceFill(['status' => 'cancelled', 'cancelled_at' => now()])->save();
            ContractAmendments::close($locked);
            foreach ([$locked->client_id, $locked->freelancer_id] as $recipient) {
                self::notify($locked, 'contract_refunded', $recipient, null);
            }
        }, 3);
    }

    private function other(Contract $contract, int $user): int
    {
        return $user === $contract->client_id ? $contract->freelancer_id : $contract->client_id;
    }

    private static function notify(Contract $contract, string $kind, int $recipient, ?int $actor): void
    {
        $title = $contract->agreement['project_title'] ?? null;
        $name = $actor === null ? null : ($actor === $contract->client_id ? $contract->agreement['client_name'] ?? null : $contract->agreement['freelancer_name'] ?? null);
        DB::afterCommit(fn () => User::query()->find($recipient)?->notify(new WorkspaceEvent($kind, '/contracts/'.$contract->id, $title, $name)));
    }
}

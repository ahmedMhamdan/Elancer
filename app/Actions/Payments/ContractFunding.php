<?php

namespace App\Actions\Payments;

use App\Enums\AccountStatus;
use App\Models\Contract;
use App\Models\PaymentAttempt;
use App\Models\PaymentEvent;
use App\Models\User;
use App\Notifications\WorkspaceEvent;
use App\Payments\Gateways;
use App\Payments\Money;
use App\Payments\Verification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ContractFunding
{
    public function __construct(private Gateways $gateways) {}

    public static function paused(Contract $contract): bool
    {
        return User::query()->whereIn('id', [$contract->client_id, $contract->freelancer_id])->where('status', '!=', AccountStatus::Active->value)->exists();
    }

    /**
     * Agreed amounts only: what awaits funding and what a verified test payment funded. Never a balance.
     *
     * @return array<string, array<string, array{count: int, total: string}>>
     */
    public static function summary(int $user): array
    {
        $summary = [];
        foreach (['client' => 'client_id', 'freelancer' => 'freelancer_id'] as $role => $column) {
            $minor = ['awaiting' => 0, 'funded' => 0];
            $count = ['awaiting' => 0, 'funded' => 0];
            foreach (Contract::query()->where($column, $user)->get(['id', 'status', 'agreement', 'funded_at']) as $contract) {
                $group = $contract->funded_at ? 'funded' : ($contract->status === 'awaiting_payment' ? 'awaiting' : null);
                if ($group !== null) {
                    $minor[$group] += Money::minor((string) $contract->agreement['amount']);
                    $count[$group]++;
                }
            }
            $summary[$role] = ['awaiting' => ['count' => $count['awaiting'], 'total' => Money::decimal($minor['awaiting'])],
                'funded' => ['count' => $count['funded'], 'total' => Money::decimal($minor['funded'])]];
        }

        return $summary;
    }

    /** Persist the attempt before any provider call. Retries and a still-open attempt return the same record. */
    public function start(Contract $contract, string $provider, string $token): PaymentAttempt
    {
        return DB::transaction(function () use ($contract, $provider, $token): PaymentAttempt {
            // Same participant-then-record order as offer acceptance and suspension.
            $users = User::query()->whereIn('id', [$contract->client_id, $contract->freelancer_id])->orderBy('id')->lockForUpdate()->get();
            $locked = Contract::query()->lockForUpdate()->findOrFail($contract->id);
            $existing = PaymentAttempt::query()->where('payer_id', $locked->client_id)->where('client_token', $token)->first();
            if ($existing) {
                if ($existing->contract_id !== $locked->id) {
                    throw ValidationException::withMessages(['payment' => __('This payment request was already used. Reload before trying again.')]);
                }

                return $existing;
            }
            if ($locked->status !== 'awaiting_payment') {
                throw ValidationException::withMessages(['payment' => __('This contract is not awaiting funding.')]);
            }
            $open = PaymentAttempt::query()->where('open_contract_id', $locked->id)->first();
            if ($open) {
                return $open;
            }
            // Q76: either participant's restriction pauses new funding; attempts already underway still reconcile.
            if ($users->count() !== 2 || $users->contains(fn (User $user) => $user->status !== AccountStatus::Active)) {
                throw ValidationException::withMessages(['payment' => __('Funding is paused while an account on this contract is restricted.')]);
            }
            $attempt = new PaymentAttempt;
            $attempt->forceFill(['reference' => (string) Str::uuid(), 'contract_id' => $locked->id, 'open_contract_id' => $locked->id,
                'payer_id' => $locked->client_id, 'client_token' => $token, 'provider' => $provider, 'environment' => 'sandbox', 'status' => 'pending',
                'amount_minor' => Money::minor((string) $locked->agreement['amount']), 'currency' => (string) $locked->agreement['currency']])->save();

            return $attempt;
        }, 3);
    }

    /** Provider creation is idempotent on the attempt reference, so resuming returns the same checkout. */
    public function checkout(PaymentAttempt $attempt): string
    {
        try {
            $checkout = $this->gateways->for($attempt->provider)->create($attempt);
        } catch (\Throwable $exception) {
            report($exception);
            if ($attempt->provider_reference === null) {
                DB::transaction(function () use ($attempt): void {
                    Contract::query()->lockForUpdate()->findOrFail($attempt->contract_id);
                    $current = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
                    if ($current->status === 'pending' && $current->provider_reference === null) {
                        $this->close($current, 'failed', 'provider_unavailable');
                    }
                });
            }
            throw ValidationException::withMessages(['payment' => __('The payment provider could not be reached. No payment was started; try again.')]);
        }
        PaymentAttempt::query()->whereKey($attempt->id)->whereNull('provider_reference')->update(['provider_reference' => $checkout->providerReference]);

        return $checkout->url;
    }

    /** Ask the provider, outside any lock, then apply the answer once. Provider errors propagate to the caller. */
    public function reconcile(PaymentAttempt $attempt): PaymentAttempt
    {
        $attempt = $attempt->fresh() ?? $attempt;
        if ($attempt->status !== 'pending' || $attempt->provider_reference === null) {
            return $attempt;
        }
        $result = $this->gateways->for($attempt->provider)->verify($attempt);
        [$current, $activated] = DB::transaction(function () use ($attempt, $result): array {
            $contract = Contract::query()->lockForUpdate()->findOrFail($attempt->contract_id);
            $current = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($current->status !== 'pending' || $result->state === Verification::PENDING) {
                return [$current, false];
            }
            if ($result->state === Verification::FAILED) {
                $this->close($current, 'failed', $result->reason);

                return [$current, false];
            }
            // Browser input never reaches here: these values come from the provider and must match what was persisted.
            if ($result->amountMinor !== $current->amount_minor || $result->currency !== $current->currency || $result->applicationReference !== $current->reference) {
                $this->close($current, 'failed', 'mismatch');

                return [$current, false];
            }
            if (PaymentEvent::query()->where('provider', $current->provider)->where('environment', $current->environment)->where('event_reference', $result->eventReference)->exists()) {
                return [$current, false];
            }
            $event = new PaymentEvent;
            $event->forceFill(['payment_attempt_id' => $current->id, 'provider' => $current->provider, 'environment' => $current->environment,
                'event_reference' => $result->eventReference, 'type' => 'capture', 'amount_minor' => $result->amountMinor, 'currency' => $result->currency])->save();
            if ($contract->status !== 'awaiting_payment') {
                // A verified payment that cannot fund this contract stays visible for review; it is never silently applied.
                $this->close($current, 'unapplied', 'contract_not_awaiting');

                return [$current, false];
            }
            $current->forceFill(['verified_at' => now()]);
            $this->close($current, 'succeeded');
            // Q80: consecutive 24-hour days from verified funding to the first complete delivery.
            $contract->forceFill(['status' => 'active', 'funded_at' => now(), 'delivery_due_at' => now()->addDays((int) $contract->agreement['duration_days'])])->save();

            return [$current, true];
        }, 3);
        if ($activated) {
            $contract = $current->contract;
            $title = $contract->agreement['project_title'] ?? null;
            User::query()->find($contract->freelancer_id)?->notify(new WorkspaceEvent('contract_funded', '/contracts/'.$contract->id, $title, $contract->agreement['client_name'] ?? null));
            User::query()->find($contract->client_id)?->notify(new WorkspaceEvent('payment_verified', '/contracts/'.$contract->id, $title));
        }

        return $current;
    }

    /** Cancelling asks the provider first: a payment that already completed is reconciled instead. */
    public function cancel(PaymentAttempt $attempt): PaymentAttempt
    {
        $attempt = $attempt->fresh() ?? $attempt;
        if ($attempt->status !== 'pending') {
            return $attempt;
        }
        if ($attempt->provider_reference !== null && ! $this->gateways->for($attempt->provider)->void($attempt)) {
            return $this->reconcile($attempt);
        }

        return DB::transaction(function () use ($attempt): PaymentAttempt {
            Contract::query()->lockForUpdate()->findOrFail($attempt->contract_id);
            $current = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($current->status === 'pending') {
                $this->close($current, 'cancelled', 'cancelled');
            }

            return $current;
        }, 3);
    }

    private function close(PaymentAttempt $attempt, string $status, ?string $reason = null): void
    {
        $attempt->forceFill(['status' => $status, 'failure_reason' => $reason, 'open_contract_id' => null, 'closed_at' => now()])->save();
    }
}

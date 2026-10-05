<?php

namespace App\Payments;

use App\Models\PaymentAttempt;
use App\Models\SimulatedPayment;
use Illuminate\Support\Str;

/**
 * Local stand-in for an external provider. Its own table is the "provider side":
 * the application learns the outcome only by verifying through this gateway.
 */
class SimulatorGateway implements PaymentGateway
{
    public function create(PaymentAttempt $attempt): Checkout
    {
        $payment = SimulatedPayment::query()->where('application_reference', $attempt->reference)->first();
        if (! $payment) {
            $payment = new SimulatedPayment;
            $payment->forceFill(['reference' => 'SIM-'.Str::upper(Str::random(20)), 'application_reference' => $attempt->reference,
                'amount_minor' => $attempt->amount_minor, 'currency' => $attempt->currency])->save();
        }

        return new Checkout($payment->reference, route('payments.simulator', $attempt));
    }

    public function verify(PaymentAttempt $attempt): Verification
    {
        $payment = SimulatedPayment::query()->where('reference', $attempt->provider_reference)->first();

        return match ($payment?->status) {
            'approved' => new Verification(Verification::PAID, $payment->reference, $payment->amount_minor, $payment->currency, $payment->application_reference),
            'declined' => Verification::failed('declined'),
            'voided' => Verification::failed('cancelled'),
            null => Verification::failed('unknown_payment'),
            default => Verification::pending(),
        };
    }

    public function void(PaymentAttempt $attempt): bool
    {
        SimulatedPayment::query()->where('reference', $attempt->provider_reference)->where('status', 'created')->update(['status' => 'voided']);

        return SimulatedPayment::query()->where('reference', $attempt->provider_reference)->value('status') !== 'approved';
    }

    public function refund(PaymentAttempt $attempt, string $capture, ?string $refund = null, ?string $failed = null): Refund
    {
        SimulatedPayment::query()->where('reference', $capture)->where('status', 'approved')->update(['status' => 'refunded']);
        $payment = SimulatedPayment::query()->where('reference', $capture)->first();

        return $payment?->status === 'refunded'
            ? new Refund(Refund::SUCCEEDED, 'R-'.$payment->reference, $payment->amount_minor, $payment->currency)
            : Refund::failed('unknown_payment');
    }

    /** The payer's decision on the simulator page. Only an undecided payment can change. */
    public function decide(PaymentAttempt $attempt, bool $approve): void
    {
        SimulatedPayment::query()->where('reference', $attempt->provider_reference)->where('status', 'created')
            ->update(['status' => $approve ? 'approved' : 'declined']);
    }
}

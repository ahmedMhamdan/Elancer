<?php

namespace App\Payments;

use App\Models\PaymentAttempt;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Stripe Checkout Sessions with a test-mode key only. A live key is refused before
 * any request, and a session Stripe reports as live is never accepted.
 */
class StripeGateway implements PaymentGateway
{
    public static function configured(): bool
    {
        return str_starts_with((string) config('payments.stripe.secret'), 'sk_test_');
    }

    public function create(PaymentAttempt $attempt): Checkout
    {
        // The idempotency key makes a retried or resumed start return the same session.
        $session = $this->api()->withHeaders(['Idempotency-Key' => $attempt->reference])->asForm()->post('/v1/checkout/sessions', [
            'mode' => 'payment',
            'client_reference_id' => $attempt->reference,
            'success_url' => route('payments.return', $attempt),
            'cancel_url' => route('payments.return', $attempt),
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($attempt->currency),
                    'unit_amount' => $attempt->amount_minor,
                    'product_data' => ['name' => 'Elancer contract #'.$attempt->contract_id],
                ],
            ]],
        ])->throw()->json();
        if (! is_string($session['id'] ?? null) || ! is_string($session['url'] ?? null)) {
            throw new \RuntimeException('Stripe did not return a checkout address.');
        }

        return new Checkout($session['id'], $session['url']);
    }

    public function verify(PaymentAttempt $attempt): Verification
    {
        $session = $this->session($attempt);
        if (($session['livemode'] ?? true) !== false) {
            return Verification::failed('mismatch');
        }
        if (($session['status'] ?? null) === 'expired') {
            return Verification::failed('cancelled');
        }
        if (($session['status'] ?? null) !== 'complete' || ($session['payment_status'] ?? null) !== 'paid') {
            return Verification::pending();
        }

        return new Verification(Verification::PAID, (string) ($session['payment_intent'] ?? $session['id']), (int) $session['amount_total'],
            strtoupper((string) $session['currency']), $session['client_reference_id'] ?? null);
    }

    public function void(PaymentAttempt $attempt): bool
    {
        $session = $this->session($attempt);
        if (($session['status'] ?? null) === 'open') {
            // Expiring closes the checkout page so the payer cannot complete it later.
            $session = $this->api()->asForm()->post('/v1/checkout/sessions/'.$attempt->provider_reference.'/expire')->throw()->json();
        }

        return ($session['status'] ?? null) !== 'complete';
    }

    public function refund(PaymentAttempt $attempt, string $capture, ?string $refund = null, ?string $failed = null): Refund
    {
        // The capture reference is the payment intent recorded at verification.
        $result = $refund !== null
            ? $this->api()->get('/v1/refunds/'.$refund)->throw()->json()
            : $this->api()->withHeaders(['Idempotency-Key' => $attempt->reference.'-refund'.($failed === null ? '' : '-'.$failed)])->asForm()
                ->post('/v1/refunds', ['payment_intent' => $capture, 'metadata' => ['reference' => $attempt->reference]])->throw()->json();
        $reference = is_string($result['id'] ?? null) ? $result['id'] : $refund;

        return match ($result['status'] ?? null) {
            'succeeded' => new Refund(Refund::SUCCEEDED, $reference, (int) $result['amount'], strtoupper((string) $result['currency'])),
            'failed', 'canceled' => Refund::failed('declined', $reference),
            default => new Refund(Refund::PENDING, $reference),
        };
    }

    /** @return array<string, mixed> */
    private function session(PaymentAttempt $attempt): array
    {
        return $this->api()->get('/v1/checkout/sessions/'.$attempt->provider_reference)->throw()->json();
    }

    private function api(): PendingRequest
    {
        if (! self::configured()) {
            throw new \RuntimeException('Stripe requires a test-mode secret key.');
        }

        return Http::baseUrl('https://api.stripe.com')->withToken((string) config('payments.stripe.secret'))->acceptJson()->connectTimeout(3)->timeout(10);
    }
}

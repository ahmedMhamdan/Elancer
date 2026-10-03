<?php

namespace App\Payments;

use App\Models\PaymentAttempt;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Moyasar (Saudi Arabia) hosted invoices with a test secret key only. Moyasar's responses carry
 * no live/test flag, so the sk_test_ key check is the guard: a live key is refused before any request.
 * Reference: https://docs.moyasar.com/api/invoices/01-create-invoice
 */
class MoyasarGateway implements PaymentGateway
{
    public static function configured(): bool
    {
        return str_starts_with((string) config('payments.moyasar.secret'), 'sk_test_');
    }

    public function create(PaymentAttempt $attempt): Checkout
    {
        // Moyasar has no idempotency key, so a resumed attempt reuses its invoice instead of creating another.
        $invoice = $attempt->provider_reference !== null ? $this->invoice($attempt) : $this->api()->asJson()->post('/v1/invoices', [
            'amount' => $attempt->amount_minor,
            'currency' => $attempt->currency,
            'description' => 'Elancer contract #'.$attempt->contract_id,
            'success_url' => route('payments.return', $attempt),
            'back_url' => route('payments.return', $attempt),
            'metadata' => ['reference' => $attempt->reference],
        ])->throw()->json();
        if (! is_string($invoice['id'] ?? null) || ! is_string($invoice['url'] ?? null)) {
            throw new \RuntimeException('Moyasar did not return a checkout address.');
        }

        return new Checkout($invoice['id'], $invoice['url']);
    }

    public function verify(PaymentAttempt $attempt): Verification
    {
        $invoice = $this->invoice($attempt);
        $status = $invoice['status'] ?? null;
        if (in_array($status, ['canceled', 'expired', 'voided'], true)) {
            return Verification::failed('cancelled');
        }
        if (in_array($status, ['failed', 'refunded'], true)) {
            return Verification::failed('declined');
        }
        if ($status !== 'paid') {
            return Verification::pending();
        }

        return new Verification(Verification::PAID, (string) $invoice['id'], (int) $invoice['amount'],
            strtoupper((string) $invoice['currency']), $invoice['metadata']['reference'] ?? null);
    }

    public function void(PaymentAttempt $attempt): bool
    {
        if (($this->invoice($attempt)['status'] ?? null) === 'initiated') {
            // Cancelling closes the hosted page so the payer cannot complete it later.
            $this->api()->put('/v1/invoices/'.$attempt->provider_reference.'/cancel');
        }

        return ($this->invoice($attempt)['status'] ?? null) !== 'paid';
    }

    /** @return array<string, mixed> */
    private function invoice(PaymentAttempt $attempt): array
    {
        return $this->api()->get('/v1/invoices/'.$attempt->provider_reference)->throw()->json();
    }

    private function api(): PendingRequest
    {
        if (! self::configured()) {
            throw new \RuntimeException('Moyasar requires a test secret key.');
        }

        // Moyasar authenticates with the secret key as the basic-auth username and an empty password.
        return Http::baseUrl('https://api.moyasar.com')->withBasicAuth((string) config('payments.moyasar.secret'), '')->acceptJson()->connectTimeout(3)->timeout(15);
    }
}

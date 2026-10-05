<?php

namespace App\Payments;

use App\Models\PaymentAttempt;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * PayPal Orders v2 against the sandbox host only (config/payments.php pins the host).
 * An approved order moves no money until this server captures it.
 */
class PayPalGateway implements PaymentGateway
{
    public function create(PaymentAttempt $attempt): Checkout
    {
        $order = $this->api()->withHeaders(['PayPal-Request-Id' => $attempt->reference])->post('/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'custom_id' => $attempt->reference,
                'amount' => ['currency_code' => $attempt->currency, 'value' => Money::decimal($attempt->amount_minor)],
            ]],
            'payment_source' => ['paypal' => ['experience_context' => [
                'user_action' => 'PAY_NOW', 'shipping_preference' => 'NO_SHIPPING',
                'return_url' => route('payments.return', $attempt), 'cancel_url' => route('payments.return', $attempt),
            ]]],
        ])->throw()->json();
        $url = null;
        foreach (is_array($order['links'] ?? null) ? $order['links'] : [] as $link) {
            if (in_array($link['rel'] ?? null, ['payer-action', 'approve'], true)) {
                $url = $link['href'] ?? null;
                break;
            }
        }
        if (! is_string($order['id'] ?? null) || ! is_string($url)) {
            throw new \RuntimeException('PayPal did not return a checkout address.');
        }

        return new Checkout($order['id'], $url);
    }

    public function verify(PaymentAttempt $attempt): Verification
    {
        $order = $this->order($attempt);
        if (($order['status'] ?? null) === 'APPROVED') {
            // The request id makes a repeated capture return the first result instead of charging again.
            $order = $this->api()->withHeaders(['PayPal-Request-Id' => $attempt->reference.'-capture'])
                ->withBody('{}', 'application/json')->post('/v2/checkout/orders/'.$attempt->provider_reference.'/capture')->throw()->json();
        }
        if (($order['status'] ?? null) === 'VOIDED') {
            return Verification::failed('cancelled');
        }
        $unit = $order['purchase_units'][0] ?? [];
        $capture = $unit['payments']['captures'][0] ?? null;
        if (($order['status'] ?? null) !== 'COMPLETED' || ! is_array($capture)) {
            return Verification::pending();
        }
        if (in_array($capture['status'] ?? null, ['DECLINED', 'FAILED'], true)) {
            return Verification::failed('declined');
        }
        if (($capture['status'] ?? null) !== 'COMPLETED') {
            return Verification::pending();
        }
        $merchant = config('payments.paypal.merchant_id');
        if ($merchant && ($unit['payee']['merchant_id'] ?? null) !== $merchant) {
            return Verification::failed('mismatch');
        }

        return new Verification(Verification::PAID, (string) $capture['id'], Money::minor((string) $capture['amount']['value']),
            (string) $capture['amount']['currency_code'], $capture['custom_id'] ?? $unit['custom_id'] ?? null);
    }

    public function void(PaymentAttempt $attempt): bool
    {
        // An unapproved or uncaptured order simply expires; only a completed capture is final.
        return ($this->order($attempt)['status'] ?? null) !== 'COMPLETED';
    }

    public function refund(PaymentAttempt $attempt, string $capture, ?string $refund = null, ?string $failed = null): Refund
    {
        // The request id makes a repeated refund return the first result instead of refunding again.
        $result = $refund !== null
            ? $this->api()->get('/v2/payments/refunds/'.$refund)->throw()->json()
            : $this->api()->withHeaders(['PayPal-Request-Id' => $attempt->reference.'-refund'.($failed === null ? '' : '-'.$failed)])
                ->withBody('{}', 'application/json')->post('/v2/payments/captures/'.$capture.'/refund')->throw()->json();
        $reference = is_string($result['id'] ?? null) ? $result['id'] : $refund;

        return match ($result['status'] ?? null) {
            'COMPLETED' => new Refund(Refund::SUCCEEDED, $reference, Money::minor((string) ($result['amount']['value'] ?? '0')), (string) ($result['amount']['currency_code'] ?? '')),
            'FAILED', 'CANCELLED' => Refund::failed('declined', $reference),
            default => new Refund(Refund::PENDING, $reference),
        };
    }

    /** @return array<string, mixed> */
    private function order(PaymentAttempt $attempt): array
    {
        return $this->api()->get('/v2/checkout/orders/'.$attempt->provider_reference)->throw()->json();
    }

    private function api(): PendingRequest
    {
        $base = config('payments.paypal.base_url');
        $token = Http::asForm()->withBasicAuth((string) config('payments.paypal.client_id'), (string) config('payments.paypal.secret'))
            ->connectTimeout(3)->timeout(10)->post($base.'/v1/oauth2/token', ['grant_type' => 'client_credentials'])->throw()->json('access_token');

        return Http::baseUrl($base)->withToken((string) $token)->acceptJson()->connectTimeout(3)->timeout(10);
    }
}

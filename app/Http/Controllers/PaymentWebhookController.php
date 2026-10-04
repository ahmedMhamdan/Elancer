<?php

namespace App\Http\Controllers;

use App\Actions\Payments\ContractFunding;
use App\Models\PaymentAttempt;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymentWebhookController extends Controller
{
    /**
     * A signed Stripe event only says which checkout to look at. The outcome still comes
     * from asking Stripe directly, so the payload itself never funds a contract.
     */
    public function stripe(Request $request, ContractFunding $funding): Response
    {
        $secret = (string) config('payments.stripe.webhook_secret');
        abort_if($secret === '', 404);
        abort_unless($this->signed($request, $secret), 400);
        $session = $request->json('data.object.id');
        if (is_string($session) && str_starts_with((string) $request->json('type'), 'checkout.session.')) {
            $attempt = PaymentAttempt::query()->where('provider', 'stripe')->where('provider_reference', $session)->first();
            if ($attempt) {
                // A provider error becomes a 500, so Stripe delivers the event again later.
                $funding->reconcile($attempt);
            }
        }

        return response()->noContent();
    }

    /** Stripe-Signature: t=<unix time>,v1=<HMAC-SHA256 of "t.payload">, accepted within five minutes. */
    private function signed(Request $request, string $secret): bool
    {
        $time = null;
        $signatures = [];
        foreach (explode(',', (string) $request->header('Stripe-Signature')) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key === 't') {
                $time = $value;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }
        if ($time === null || ! ctype_digit($time) || abs(time() - (int) $time) > 300) {
            return false;
        }
        $expected = hash_hmac('sha256', $time.'.'.$request->getContent(), $secret);

        return collect($signatures)->contains(fn (string $signature) => hash_equals($expected, $signature));
    }
}

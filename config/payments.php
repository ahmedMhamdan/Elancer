<?php

return [

    /*
    | Every provider here is a test provider. No live endpoint or live credential
    | path exists: PayPal is pinned to its sandbox host. Real money is outside
    | Elancer's scope.
    |
    | The simulator is an offline stand-in for automated tests. It stays off
    | unless explicitly enabled, so members only ever see real test providers.
    */

    'simulator' => [
        'enabled' => (bool) env('PAYMENT_SIMULATOR', false),
    ],

    'stripe' => [
        // Only a test-mode key (sk_test_...) is accepted; anything else leaves Stripe switched off.
        'secret' => env('STRIPE_TEST_SECRET_KEY'),
        // Optional signing secret (whsec_...) for /payments/webhooks/stripe; the endpoint is off without it.
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'moyasar' => [
        // Moyasar test secret key (sk_test_...); anything else leaves Moyasar switched off.
        'secret' => env('MOYASAR_TEST_SECRET_KEY'),
    ],

    'paypal' => [
        'base_url' => 'https://api-m.sandbox.paypal.com',
        'client_id' => env('PAYPAL_SANDBOX_CLIENT_ID'),
        'secret' => env('PAYPAL_SANDBOX_SECRET'),
        // Optional: the sandbox business account expected to receive the payment.
        'merchant_id' => env('PAYPAL_SANDBOX_MERCHANT_ID'),
    ],

];

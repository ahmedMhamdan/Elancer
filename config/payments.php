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

    'paypal' => [
        'base_url' => 'https://api-m.sandbox.paypal.com',
        'client_id' => env('PAYPAL_SANDBOX_CLIENT_ID'),
        'secret' => env('PAYPAL_SANDBOX_SECRET'),
        // Optional: the sandbox business account expected to receive the payment.
        'merchant_id' => env('PAYPAL_SANDBOX_MERCHANT_ID'),
    ],

];

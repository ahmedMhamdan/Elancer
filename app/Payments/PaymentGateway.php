<?php

namespace App\Payments;

use App\Models\PaymentAttempt;

/**
 * Implementations call the provider over HTTP. Never call them while holding a database lock.
 */
interface PaymentGateway
{
    /** Create the provider-side payment and return where the payer continues. */
    public function create(PaymentAttempt $attempt): Checkout;

    /** Read (and where the provider requires it, settle) the provider-side state. */
    public function verify(PaymentAttempt $attempt): Verification;

    /** True when the provider-side payment can no longer complete; false when it already completed. */
    public function void(PaymentAttempt $attempt): bool;
}

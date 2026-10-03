<?php

namespace App\Payments;

final readonly class Checkout
{
    public function __construct(public string $providerReference, public string $url) {}
}

<?php

namespace App\Payments;

class Gateways
{
    /** @return list<string> Providers a payer may choose now, in display order. */
    public function available(): array
    {
        return array_values(array_filter([
            config('payments.paypal.client_id') && config('payments.paypal.secret') ? 'paypal' : null,
            config('payments.simulator.enabled') ? 'simulator' : null,
        ]));
    }

    /** Verification must keep working for an attempt whose provider was later switched off. */
    public function for(string $provider): PaymentGateway
    {
        return match ($provider) {
            'paypal' => app(PayPalGateway::class),
            'simulator' => app(SimulatorGateway::class),
            default => throw new \InvalidArgumentException('Unknown payment provider.'),
        };
    }
}

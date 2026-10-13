<?php

namespace App\Payments;

use App\Actions\Auth\DemoAccess;
use App\Actions\Demo\DemoWorld;
use Illuminate\Validation\ValidationException;

class Gateways
{
    /** @return list<string> Providers a payer may choose now, in display order. */
    public function available(): array
    {
        // Plan 027: a demo session pays through the stand-in provider alone, so nothing a visitor does reaches a real test provider.
        if (DemoAccess::active(request())) {
            return DemoWorld::acting(request()) ? ['simulator'] : [];
        }

        return array_values(array_filter([
            StripeGateway::configured() ? 'stripe' : null,
            MoyasarGateway::configured() ? 'moyasar' : null,
            config('payments.simulator.enabled') ? 'simulator' : null,
        ]));
    }

    /** Verification must keep working for an attempt whose provider was later switched off. */
    public function for(string $provider): PaymentGateway
    {
        if ($provider !== 'simulator' && DemoAccess::active(request())) {
            throw ValidationException::withMessages(['payment' => __('This payment went through a test provider the shared demo cannot use.')]);
        }

        return match ($provider) {
            'stripe' => app(StripeGateway::class),
            'moyasar' => app(MoyasarGateway::class),
            'simulator' => app(SimulatorGateway::class),
            default => throw new \InvalidArgumentException('Unknown payment provider.'),
        };
    }
}

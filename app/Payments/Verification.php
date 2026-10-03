<?php

namespace App\Payments;

final readonly class Verification
{
    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const FAILED = 'failed';

    public function __construct(
        public string $state,
        public ?string $eventReference = null,
        public ?int $amountMinor = null,
        public ?string $currency = null,
        public ?string $applicationReference = null,
        public ?string $reason = null,
    ) {}

    public static function pending(): self
    {
        return new self(self::PENDING);
    }

    public static function failed(string $reason): self
    {
        return new self(self::FAILED, reason: $reason);
    }
}

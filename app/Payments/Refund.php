<?php

namespace App\Payments;

final readonly class Refund
{
    public const PENDING = 'pending';

    public const SUCCEEDED = 'succeeded';

    public const FAILED = 'failed';

    public function __construct(
        public string $state,
        public ?string $reference = null,
        public ?int $amountMinor = null,
        public ?string $currency = null,
        public ?string $reason = null,
    ) {}

    public static function failed(string $reason, ?string $reference = null): self
    {
        return new self(self::FAILED, $reference, reason: $reason);
    }
}

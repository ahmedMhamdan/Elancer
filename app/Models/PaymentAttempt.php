<?php

namespace App\Models;

use App\Payments\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $reference
 * @property int $contract_id
 * @property int $payer_id
 * @property int|null $open_contract_id
 * @property string $provider
 * @property string $environment
 * @property int $amount_minor
 * @property string $currency
 * @property string $status
 * @property string|null $provider_reference
 * @property string|null $failure_reason
 * @property CarbonImmutable|null $verified_at
 * @property CarbonImmutable|null $created_at
 * @property-read Contract $contract
 */
class PaymentAttempt extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'verified_at' => 'immutable_datetime', 'closed_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $attempt): void {
            if ($attempt->isDirty(['reference', 'contract_id', 'payer_id', 'provider', 'environment', 'amount_minor', 'currency', 'client_token'])) {
                throw new \LogicException('Payment attempt expectations are immutable.');
            }
        });
    }

    /**
     * Participant-safe view: no client token, payer id or provider-side identifiers.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return ['id' => $this->id, 'reference' => strtoupper(substr($this->reference, 0, 8)), 'provider' => $this->provider,
            'status' => $this->status, 'failure_reason' => $this->failure_reason, 'amount' => Money::decimal($this->amount_minor),
            'currency' => $this->currency, 'created_at' => $this->created_at, 'verified_at' => $this->verified_at];
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}

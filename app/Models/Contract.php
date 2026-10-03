<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $project_id
 * @property int $proposal_id
 * @property int $offer_id
 * @property int $client_id
 * @property int $freelancer_id
 * @property int $conversation_id
 * @property string $status
 * @property array<string, mixed> $agreement
 * @property CarbonImmutable|null $funded_at
 * @property CarbonImmutable|null $delivery_due_at
 */
class Contract extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['agreement' => 'array', 'funded_at' => 'immutable_datetime', 'delivery_due_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $contract): void {
            if ($contract->isDirty(['agreement', 'project_id', 'proposal_id', 'offer_id', 'client_id', 'freelancer_id', 'conversation_id'])) {
                throw new \LogicException('Accepted agreement attribution and terms are immutable.');
            }
        });
    }

    public function contains(int $user): bool
    {
        return $user === $this->client_id || $user === $this->freelancer_id;
    }
}

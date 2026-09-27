<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $project_id
 * @property int $proposal_id
 * @property int $client_id
 * @property int $freelancer_id
 * @property int $version
 * @property string $status
 * @property string|null $reason
 * @property array<string, mixed> $terms
 * @property CarbonImmutable $expires_at
 * @property-read Project $project
 */
class Offer extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['terms' => 'array', 'version' => 'integer', 'expires_at' => 'immutable_datetime', 'closed_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $offer): void {
            if ($offer->isDirty(['terms', 'project_id', 'proposal_id', 'client_id', 'freelancer_id', 'expires_at', 'client_token'])) {
                throw new \LogicException('Sent offer terms and attribution are immutable.');
            }
        });
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function contains(int $user): bool
    {
        return $user === $this->client_id || $user === $this->freelancer_id;
    }
}

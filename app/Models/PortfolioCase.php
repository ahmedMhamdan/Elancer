<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $contract_id
 * @property array<string, mixed> $content
 * @property array<string, mixed>|null $public_content
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $hidden_at
 * @property CarbonImmutable|null $revoked_at
 * @property CarbonImmutable|null $moderated_at
 * @property int|null $moderated_by
 * @property CarbonImmutable $created_at
 * @property-read Contract|null $contract
 * @property-read Collection<int, PortfolioApproval> $approvals
 */
class PortfolioCase extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['content' => 'array', 'public_content' => 'array', 'published_at' => 'immutable_datetime',
            'hidden_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime', 'moderated_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    /**
     * Q74: the one public boundary. A case needs a public version, must not be hidden by its
     * owner or by moderation, and its owner's profile must be publicly visible. Withdrawn permission removes the public version.
     *
     * @param  Builder<PortfolioCase>  $query
     */
    public function scopePubliclyVisible(Builder $query): void
    {
        $query->whereNotNull('public_content')->whereNull('hidden_at')->whereNull('moderated_at')
            ->whereIn('user_id', Profile::query()->publiclyVisible()->select('user_id'));
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /** @return HasMany<PortfolioApproval, $this> */
    public function approvals(): HasMany
    {
        return $this->hasMany(PortfolioApproval::class)->orderBy('id');
    }
}

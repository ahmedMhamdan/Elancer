<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $project_id
 * @property int $user_id
 * @property string $status
 * @property string $organization
 * @property string|null $client_note
 * @property array<string, mixed>|null $draft
 * @property array<string, mixed>|null $content
 * @property array<string, mixed>|null $profile_snapshot
 * @property int $version
 * @property CarbonImmutable|null $submitted_at
 * @property-read Project $project
 */
class Proposal extends Model
{
    protected $guarded = ['*'];

    protected $hidden = ['draft', 'client_note', 'profile_snapshot'];

    protected function casts(): array
    {
        return ['draft' => 'array', 'content' => 'array', 'profile_snapshot' => 'array', 'version' => 'integer', 'submitted_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<ProposalEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ProposalEvent::class);
    }
}

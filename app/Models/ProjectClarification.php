<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $project_id
 * @property string $body
 * @property CarbonImmutable $created_at
 */
class ProjectClarification extends Model
{
    public const UPDATED_AT = null;

    /** Each clarification notifies every current applicant, so a project carries a bounded number. */
    public const LIMIT = 20;

    // Written only by the owner's server action; never edited afterwards.
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}

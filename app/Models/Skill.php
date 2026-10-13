<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * @property int $id
 * @property string $name
 * @property CarbonImmutable|null $retired_at
 */
class Skill extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['retired_at' => 'immutable_datetime'];
    }

    /**
     * Plan 026: what a member is offered. A retired skill stays wherever it already is.
     *
     * @param  Builder<Skill>  $query
     */
    public function scopeSelectable(Builder $query): void
    {
        $query->whereNull('retired_at');
    }

    /**
     * The rule for a chosen skill: in the catalog and not retired, unless the record being saved already carries it.
     *
     * @param  array<int, int|string>  $kept  what is attached now, in the same column
     */
    public static function choosable(string $column, array $kept = []): Exists
    {
        return Rule::exists('skills', $column)->where(fn ($query) => $query->where(fn ($either) => $either->whereNull('retired_at')->orWhereIn($column, $kept)));
    }

    /** @return BelongsToMany<Profile, $this> */
    public function profiles(): BelongsToMany
    {
        return $this->belongsToMany(Profile::class, 'profile_skill');
    }

    /** @return BelongsToMany<Project, $this> */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class);
    }
}

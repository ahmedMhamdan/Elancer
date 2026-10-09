<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $category_id
 * @property string|null $title
 * @property string|null $description
 * @property string|null $budget_min
 * @property string|null $budget_max
 * @property int $version
 * @property list<string>|null $screening_questions
 * @property-read Collection<int, Skill> $skills
 * @property string $status
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $application_closes_at
 * @property CarbonImmutable|null $moderated_at
 * @property int|null $moderated_by
 * @property-read Category|null $category
 * @property-read User $user
 */
class Project extends Model
{
    use SoftDeletes;

    // Publication and ownership are set only by server actions.
    protected $guarded = ['*'];

    // The owner learns that moderation hid a project, never which administrator did it.
    protected $hidden = ['moderated_by'];

    protected function casts(): array
    {
        return ['screening_questions' => 'array', 'version' => 'integer', 'budget_min' => 'decimal:2', 'budget_max' => 'decimal:2', 'published_at' => 'immutable_datetime', 'application_closes_at' => 'immutable_datetime', 'moderated_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsToMany<Skill, $this> */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class);
    }

    /** @return HasMany<Proposal, $this> */
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }

    /** @return HasMany<ProjectClarification, $this> */
    public function clarifications(): HasMany
    {
        return $this->hasMany(ProjectClarification::class);
    }

    /**
     * Q68: the public boundary. A project hidden by moderation leaves public pages and lists.
     *
     * @param  Builder<Project>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->listable()->whereNull('moderated_at');
    }

    /**
     * Everything public visibility needs except moderation, so hiring already under way
     * continues on a project that moderation hid.
     *
     * @param  Builder<Project>  $query
     */
    public function scopeListable(Builder $query): void
    {
        $query->whereIn('status', ['published', 'closed', 'hired'])
            ->whereNotNull('published_at')->where('published_at', '<=', now())
            ->whereNotNull('application_closes_at')
            ->whereNotNull('title')->whereNotNull('description')
            ->where('budget_min', '>=', 1)->whereColumn('budget_max', '>=', 'budget_min')
            ->whereHas('category')
            ->whereHas('user', fn (Builder $owner) => $owner->where('status', 'active')->whereNotNull('email_verified_at'));
    }
}

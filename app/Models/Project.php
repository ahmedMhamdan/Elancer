<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
 * @property-read Category|null $category
 * @property-read User $user
 */
class Project extends Model
{
    use SoftDeletes;

    // Publication and ownership are set only by server actions.
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['screening_questions' => 'array', 'version' => 'integer', 'budget_min' => 'decimal:2', 'budget_max' => 'decimal:2', 'published_at' => 'immutable_datetime', 'application_closes_at' => 'immutable_datetime'];
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

    /** @param Builder<Project> $query */
    public function scopeVisible(Builder $query): void
    {
        $query->whereIn('status', ['published', 'closed'])
            ->whereNotNull('published_at')->where('published_at', '<=', now())
            ->whereNotNull('application_closes_at')
            ->whereNotNull('title')->whereNotNull('description')
            ->where('budget_min', '>=', 1)->whereColumn('budget_max', '>=', 'budget_min')
            ->whereHas('category')
            ->whereHas('user', fn (Builder $owner) => $owner->where('status', 'active')->whereNotNull('email_verified_at'));
    }
}

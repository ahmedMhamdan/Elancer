<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property-read Collection<int, Skill> $skillTags
 * @property string $availability
 * @property list<array{label: string, url: string}>|null $professional_links
 * @property int $id
 * @property int $user_id
 * @property string|null $headline
 * @property string|null $bio
 * @property string|null $location
 * @property string|null $country
 * @property string|null $city
 * @property string|null $company
 * @property list<string>|null $skills
 * @property string|null $photo_path
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['headline', 'bio', 'location'])]
#[Hidden(['photo_path'])]
class Profile extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'skills' => 'array',
            'professional_links' => 'array',
        ];
    }

    public function readyForPublication(): bool
    {
        return trim($this->headline ?? '') !== '' && trim($this->bio ?? '') !== '' && $this->skillTags()->exists();
    }

    /** @param Builder<Profile> $query */
    public function scopePubliclyVisible(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now())
            ->whereNotNull('headline')->whereRaw("TRIM(headline) <> ''")
            ->whereNotNull('bio')->whereRaw("TRIM(bio) <> ''")->whereHas('skillTags')
            ->whereHas('user', fn (Builder $user) => $user->where('status', 'active')->whereNotNull('email_verified_at')->whereNotNull('onboarding_completed_at'));
    }

    /** @return array<string, mixed> */
    public function publicDetails(): array
    {
        return [
            'id' => $this->id, 'name' => $this->user->name,
            'headline' => $this->headline, 'bio' => $this->bio,
            'country' => $this->country, 'availability' => $this->availability,
            'skills' => $this->skillTags->sortBy('name')->values()->map(fn (Skill $skill) => $skill->only(['id', 'name'])),
            'links' => $this->professional_links ?? [],
            'avatar' => $this->photo_path ? route('freelancers.photo', $this) : null,
            'member_since' => $this->user->created_at?->format('Y'),
        ];
    }

    /** @return BelongsToMany<Skill, $this> */
    public function skillTags(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'profile_skill');
    }

    /**
     * @param  list<string>  $names
     */
    public function syncSkillTags(array $names): void
    {
        $tags = Skill::query()->whereIn('name', $names)->get();
        $this->skillTags()->sync($tags->modelKeys());
        // Keep the existing API's ordered string array and rollback compatibility.
        $this->skills = $names;
        $this->save();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

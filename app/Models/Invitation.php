<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $project_id
 * @property int $recipient_id
 * @property string $status
 * @property int $version
 * @property CarbonImmutable|null $declined_at
 * @property CarbonImmutable $sent_at
 * @property-read Project $project
 * @property-read User $recipient
 */
class Invitation extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'declined_at' => 'immutable_datetime', 'sent_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}

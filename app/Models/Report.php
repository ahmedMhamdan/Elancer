<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $reporter_id
 * @property string $target_type
 * @property int $target_id
 * @property int|null $subject_id
 * @property int|null $conversation_id
 * @property string $reason
 * @property string $explanation
 * @property array<string, mixed> $snapshot
 * @property string $status
 * @property string|null $outcome
 * @property int|null $handler_id
 * @property string|null $open_key
 * @property CarbonImmutable|null $resolved_at
 * @property CarbonImmutable $created_at
 * @property-read User|null $reporter
 * @property-read User|null $subject
 * @property-read User|null $handler
 */
class Report extends Model
{
    public const TARGETS = ['project', 'profile', 'case', 'message', 'contract'];

    public const REASONS = ['spam_scam', 'harassment', 'inappropriate', 'off_platform', 'fake_identity', 'other'];

    /** Q66: the only outcomes a reporter is ever told. */
    public const OUTCOMES = ['action_taken', 'no_violation', 'not_confirmed', 'duplicate'];

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'resolved_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /** @return BelongsTo<User, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_id');
    }

    /** @return BelongsTo<User, $this> */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handler_id');
    }
}

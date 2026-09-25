<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int $sender_id
 * @property string $body
 * @property string $client_token
 * @property int $version
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable|null $edited_at
 * @property-read Conversation $conversation
 */
class ConversationMessage extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'created_at' => 'immutable_datetime', 'edited_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}

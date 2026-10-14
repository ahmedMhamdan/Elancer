<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $conversation_message_id
 * @property string $name
 * @property string $path
 * @property string $mime
 * @property int $size
 * @property-read ConversationMessage $message
 */
class MessageFile extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    // The stored location never leaves the server.
    protected $hidden = ['path'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    /** @return BelongsTo<ConversationMessage, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class, 'conversation_message_id');
    }
}

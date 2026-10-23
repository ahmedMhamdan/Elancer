<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $proposal_id
 * @property int $client_id
 * @property int $freelancer_id
 * @property int $client_read_through
 * @property int $freelancer_read_through
 * @property bool $client_archived
 * @property bool $freelancer_archived
 * @property CarbonImmutable|null $client_emailed_at
 * @property CarbonImmutable|null $freelancer_emailed_at
 * @property CarbonImmutable $updated_at
 * @property-read ConversationMessage|null $latestMessage
 * @property-read Contract|null $contract
 * @property-read Proposal $proposal
 * @property-read User $client
 * @property-read User $freelancer
 */
class Conversation extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['client_read_through' => 'integer', 'freelancer_read_through' => 'integer', 'client_archived' => 'boolean', 'freelancer_archived' => 'boolean', 'updated_at' => 'immutable_datetime',
            'client_emailed_at' => 'immutable_datetime', 'freelancer_emailed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Proposal, $this> */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /** @return BelongsTo<User, $this> */
    public function freelancer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'freelancer_id');
    }

    /** @return HasMany<ConversationMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }

    /**
     * The contract that grew out of this hiring conversation, once an offer was accepted.
     *
     * @return HasOne<Contract, $this>
     */
    public function contract(): HasOne
    {
        return $this->hasOne(Contract::class);
    }

    /** @return HasOne<ConversationMessage, $this> */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(ConversationMessage::class)->latestOfMany();
    }

    public function contains(int $user): bool
    {
        return $user === $this->client_id || $user === $this->freelancer_id;
    }

    public function readColumn(int $user): string
    {
        return $user === $this->client_id ? 'client_read_through' : 'freelancer_read_through';
    }

    public function archiveColumn(int $user): string
    {
        return $user === $this->client_id ? 'client_archived' : 'freelancer_archived';
    }

    public function emailedColumn(int $user): string
    {
        return $user === $this->client_id ? 'client_emailed_at' : 'freelancer_emailed_at';
    }

    /** Messages from the other participant this member has not marked read; moderation-hidden ones are not counted. */
    public function unreadFor(int $user): int
    {
        return $this->messages()->where('sender_id', '!=', $user)->whereNull('moderated_at')
            ->where('id', '>', (int) $this->getAttribute($this->readColumn($user)))->count();
    }
}

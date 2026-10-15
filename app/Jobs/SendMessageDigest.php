<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\User;
use App\Notifications\WorkspaceEventMail;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Plan 031 (plan 003 section 15): one email per unread conversation. It waits 15 minutes after the conversation's
 * bell entry, is dropped once the member has read the entry or the conversation, and goes at most once an hour per
 * recipient and conversation. It says how many messages wait, never what they say.
 */
class SendMessageDigest implements ShouldQueue
{
    use Queueable;

    public const WAIT_MINUTES = 15;

    public const SPACING_MINUTES = 60;

    public function __construct(public int $recipient, public int $conversation, public string $entry) {}

    /** Called when the bell entry is made; the check runs later, against what is true then. */
    public static function schedule(int $recipient, int $conversation, string $entry, ?CarbonImmutable $at = null): void
    {
        self::dispatch($recipient, $conversation, $entry)->delay($at ?? now()->addMinutes(self::WAIT_MINUTES))->afterCommit();
    }

    public function handle(): void
    {
        $recipient = User::query()->find($this->recipient);
        $conversation = Conversation::query()->find($this->conversation);
        if (! $recipient || ! $conversation || ! $conversation->contains($recipient->id)) {
            return;
        }
        // Opening the bell entry or marking the conversation read cancels the email.
        if (! $recipient->unreadNotifications()->whereKey($this->entry)->exists()) {
            return;
        }
        if (! $recipient->hasVerifiedEmail() || ! $recipient->hasDeliverableEmail() || ! $recipient->emailPreferences()['messages']) {
            return;
        }
        $column = $conversation->emailedColumn($recipient->id);
        /** @var array{count: int}|array{later: CarbonImmutable}|null $outcome */
        $outcome = DB::transaction(function () use ($conversation, $recipient, $column): ?array {
            // The row lock keeps two checks for the same conversation from both sending.
            $locked = Conversation::query()->lockForUpdate()->findOrFail($conversation->id);
            $unread = $locked->unreadFor($recipient->id);
            if ($unread === 0) {
                return null;
            }
            $last = $locked->getAttribute($column);
            if ($last instanceof CarbonImmutable && $last->addMinutes(self::SPACING_MINUTES)->isFuture()) {
                return ['later' => $last->addMinutes(self::SPACING_MINUTES)];
            }
            // Bookkeeping only: the inbox order follows messages, not emails.
            $locked->timestamps = false;
            $locked->forceFill([$column => now()])->save();

            return ['count' => $unread];
        }, 3);
        if ($outcome === null) {
            return;
        }
        if (isset($outcome['later'])) {
            self::schedule($this->recipient, $this->conversation, $this->entry, $outcome['later']);

            return;
        }
        $project = $conversation->proposal->project->title;
        $sender = User::query()->whereKey($recipient->id === $conversation->client_id ? $conversation->freelancer_id : $conversation->client_id)->value('name');
        $recipient->notify(new WorkspaceEventMail('message_received', '/messages/'.$conversation->id, $project, $sender, $outcome['count']));
    }
}

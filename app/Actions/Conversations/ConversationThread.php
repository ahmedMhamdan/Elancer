<?php

namespace App\Actions\Conversations;

use App\Actions\Files\AttachedFiles;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\MessageFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/** One page of a conversation as one participant reads it, for the Messages page and the workroom's tab (plan 031). */
class ConversationThread
{
    /**
     * @param  array<string, mixed>  $appends  query values every page link keeps
     * @return array{messages: mixed, writable: bool, visibleThrough: int, fileLimit: int}
     */
    public static function for(Conversation $conversation, int $user, string $pageName = 'page', array $appends = []): array
    {
        $writable = HiringAccess::conversationWritable($conversation->proposal);
        $messages = $conversation->messages()->orderByDesc('id')->paginate(30, ['*'], $pageName)->withQueryString()->appends($appends);
        $ids = $messages->getCollection()->pluck('id');
        $revisions = DB::table('message_revisions')->whereIn('conversation_message_id', $ids)->orderBy('version')->get(['conversation_message_id', 'body', 'version', 'created_at'])->groupBy('conversation_message_id');
        $files = MessageFile::query()->whereIn('conversation_message_id', $ids)->orderBy('id')->get(['id', 'conversation_message_id', 'name', 'size'])->groupBy('conversation_message_id');

        return [
            // Q68: a message hidden by moderation reaches neither participant; only a notice does.
            'messages' => $messages->through(fn (ConversationMessage $message) => $message->moderated_at !== null ? [
                ...$message->only(['id', 'version', 'created_at']),
                'body' => null, 'edited_at' => null, 'hidden' => true, 'mine' => $message->sender_id === $user, 'can_edit' => false, 'revisions' => [], 'files' => [],
            ] : [
                ...$message->only(['id', 'body', 'version', 'created_at', 'edited_at']),
                'hidden' => false,
                'mine' => $message->sender_id === $user,
                'can_edit' => $writable && $message->sender_id === $user && $message->created_at->addMinutes(15)->isFuture(),
                'revisions' => ($revisions->get($message->id) ?? collect())->map(fn ($revision) => ['body' => $revision->body, 'version' => $revision->version,
                    // Stored in UTC without a zone; sent as a model's dates are, so the browser shows the member's own time.
                    'created_at' => Date::parse($revision->created_at)->toJSON()])->values(),
                'files' => ($files->get($message->id) ?? collect())->map(fn (MessageFile $file) => $file->only(['id', 'name', 'size']))->values(),
            ]),
            'writable' => $writable,
            'visibleThrough' => (int) ($ids->max() ?? 0),
            'fileLimit' => AttachedFiles::LIMIT,
        ];
    }

    /**
     * Plan 032 part 13: the member has the conversation's newest messages on screen, so everything in it is read, and
     * so are their bell entries for it. Returns the first message that was unread until now (where the page draws its
     * "new messages" line), or null. Only the member's own count changes; nothing tells the other side (Q86).
     */
    public static function markRead(Conversation $conversation, User $user): ?int
    {
        $first = DB::transaction(function () use ($conversation, $user): ?int {
            $locked = Conversation::query()->lockForUpdate()->findOrFail($conversation->id);
            $column = $locked->readColumn($user->id);
            $through = (int) $locked->getAttribute($column);
            $first = $locked->messages()->where('sender_id', '!=', $user->id)->where('id', '>', $through)->min('id');
            $latest = (int) $locked->messages()->max('id');
            if ($latest > $through) {
                // Reading does not reorder the conversation for the other side.
                $locked->timestamps = false;
                $locked->forceFill([$column => $latest])->save();
                $conversation->setAttribute($column, $latest);
            }
            // Under the conversation's lock, so a message sent meanwhile keeps its own bell entry.
            self::notifications($user, $conversation->id)->each->markAsRead();

            return $first === null ? null : (int) $first;
        });

        return $first;
    }

    /**
     * The member's unread bell entries for one conversation.
     *
     * @return Collection<int, DatabaseNotification>
     */
    public static function notifications(User $user, int $conversation): Collection
    {
        // The stored JSON is narrowed by text so the query stays portable, then matched exactly.
        return $user->unreadNotifications()->where('data', 'like', '%"kind":"message_received"%')->get()
            ->filter(fn (DatabaseNotification $notification) => ($notification->data['conversation'] ?? null) === $conversation)->values();
    }

    /** The count the inbox shows beside a conversation. */
    public static function unread(Conversation $conversation, int $user): int
    {
        return $conversation->messages()->where('sender_id', '!=', $user)->where('id', '>', $conversation->getAttribute($conversation->readColumn($user)))->count();
    }

    /** How many inbox conversations hold a message this member has not read: the inbox's Unread filter, archived ones left out (plan 032). */
    public static function unreadConversations(int $user): int
    {
        $side = fn (string $role) => fn ($query) => $query->where($role.'_id', $user)->where($role.'_archived', false)
            ->whereHas('messages', fn ($query) => $query->where('sender_id', '!=', $user)->whereColumn('conversation_messages.id', '>', 'conversations.'.$role.'_read_through'));

        return Conversation::query()->where($side('client'))->orWhere($side('freelancer'))->count();
    }
}

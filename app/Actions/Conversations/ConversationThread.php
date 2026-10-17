<?php

namespace App\Actions\Conversations;

use App\Actions\Files\AttachedFiles;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\MessageFile;
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
                'revisions' => ($revisions->get($message->id) ?? collect())->map(fn ($revision) => ['body' => $revision->body, 'version' => $revision->version, 'created_at' => $revision->created_at])->values(),
                'files' => ($files->get($message->id) ?? collect())->map(fn (MessageFile $file) => $file->only(['id', 'name', 'size']))->values(),
            ]),
            'writable' => $writable,
            'visibleThrough' => (int) ($ids->max() ?? 0),
            'fileLimit' => AttachedFiles::LIMIT,
        ];
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

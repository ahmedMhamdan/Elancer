<?php

namespace App\Http\Controllers;

use App\Actions\Conversations\HiringAccess;
use App\Events\WorkspaceSignal;
use App\Models\Contract;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use App\Notifications\WorkspaceEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $user = $request->user()->id;

        return Inertia::render('messages/index', [
            'conversations' => $this->inbox($user, $filters)->paginate(20)->withQueryString()->through(fn (Conversation $conversation) => $this->summary($conversation, $user)),
            'archived' => $filters['archived'],
            'filters' => $filters,
        ]);
    }

    public function start(Request $request, Proposal $proposal): RedirectResponse
    {
        abort_unless($proposal->project->user_id === $request->user()->id && $proposal->submitted_at, 404);
        $data = $request->validate(['body' => ['required', 'string', 'max:10000'], 'client_token' => ['required', 'uuid']]);
        $conversation = DB::transaction(function () use ($request, $proposal, $data): Conversation {
            $this->lockParticipants($proposal->project->user_id, $proposal->user_id);
            Project::query()->lockForUpdate()->findOrFail($proposal->project_id);
            $proposal = Proposal::query()->lockForUpdate()->findOrFail($proposal->id);
            abort_unless(HiringAccess::conversationWritable($proposal), 409, __('This hiring conversation is read-only.'));
            $conversation = Conversation::query()->where('proposal_id', $proposal->id)->lockForUpdate()->first();
            if ($conversation) {
                return $conversation;
            }
            $conversation = new Conversation;
            $conversation->forceFill(['proposal_id' => $proposal->id, 'client_id' => $request->user()->id, 'freelancer_id' => $proposal->user_id])->save();
            $this->sendMessage($conversation, $request->user()->id, $data['body'], $data['client_token']);

            return $conversation;
        }, 3);

        return to_route('messages.show', $conversation);
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        abort_unless($conversation->contains($request->user()->id), 404);
        $filters = $this->filters($request);
        $conversation->loadMissing(['latestMessage']);
        $user = $request->user()->id;
        $writable = HiringAccess::conversationWritable($conversation->proposal);
        $messages = $conversation->messages()->orderByDesc('id')->paginate(30)->withQueryString();
        $revisions = DB::table('message_revisions')->whereIn('conversation_message_id', $messages->getCollection()->pluck('id'))->orderBy('version')->get(['conversation_message_id', 'body', 'version', 'created_at'])->groupBy('conversation_message_id');
        $visibleThrough = (int) ($messages->getCollection()->max('id') ?? 0);

        return Inertia::render('messages/show', [
            'conversations' => $this->inbox($user, $filters)->paginate(20, ['*'], 'list_page')->withQueryString()->through(fn (Conversation $item) => $this->summary($item, $user)),
            'filters' => $filters,
            'conversation' => $this->summary($conversation, $user), 'writable' => $writable, 'visibleThrough' => $visibleThrough,
            // Q68: a message hidden by moderation reaches neither participant; only a notice does.
            'messages' => $messages->through(fn (ConversationMessage $message) => $message->moderated_at !== null ? [
                ...$message->only(['id', 'version', 'created_at']),
                'body' => null, 'edited_at' => null, 'hidden' => true, 'mine' => $message->sender_id === $user, 'can_edit' => false, 'revisions' => [],
            ] : [
                ...$message->only(['id', 'body', 'version', 'created_at', 'edited_at']),
                'hidden' => false,
                'mine' => $message->sender_id === $user,
                'can_edit' => $writable && $message->sender_id === $user && $message->created_at->addMinutes(15)->isFuture(),
                'revisions' => ($revisions->get($message->id) ?? collect())->map(fn ($revision) => ['body' => $revision->body, 'version' => $revision->version, 'created_at' => $revision->created_at])->values(),
            ]),
        ]);
    }

    public function send(Request $request, Conversation $conversation): RedirectResponse
    {
        abort_unless($conversation->contains($request->user()->id), 404);
        $filters = $this->filters($request);
        $data = $request->validate(['body' => ['required', 'string', 'max:10000'], 'client_token' => ['required', 'uuid']]);
        DB::transaction(function () use ($request, $conversation, $data): void {
            $locked = $this->lockWritable($conversation);
            $this->sendMessage($locked, $request->user()->id, $data['body'], $data['client_token']);
        }, 3);

        return to_route('messages.show', ['conversation' => $conversation, ...array_filter($filters, fn ($value) => $value !== '' && $value !== false), ...($request->integer('list_page') > 1 ? ['list_page' => $request->integer('list_page')] : [])]);
    }

    public function edit(Request $request, ConversationMessage $message): RedirectResponse
    {
        abort_unless($message->sender_id === $request->user()->id && $message->conversation->contains($request->user()->id), 404);
        $data = $request->validate(['body' => ['required', 'string', 'max:10000'], 'version' => ['required', 'integer', 'min:1']]);
        DB::transaction(function () use ($message, $data): void {
            $this->lockWritable($message->conversation);
            $locked = ConversationMessage::query()->lockForUpdate()->findOrFail($message->id);
            abort_if($locked->moderated_at !== null, 409, __('This message was hidden by moderation.'));
            abort_unless($locked->created_at->addMinutes(15)->isFuture(), 409, __('The message correction window has ended.'));
            abort_unless($locked->version === (int) $data['version'], 409, __('This message changed. Reload before correcting it.'));
            if ($locked->body === $data['body']) {
                return;
            }
            DB::table('message_revisions')->insert(['conversation_message_id' => $locked->id, 'body' => $locked->body, 'version' => $locked->version, 'created_at' => now()]);
            $locked->forceFill(['body' => $data['body'], 'version' => $locked->version + 1, 'edited_at' => now()])->save();
        }, 3);
        $conversation = $message->conversation;
        WorkspaceSignal::send($message->sender_id === $conversation->client_id ? $conversation->freelancer_id : $conversation->client_id, conversation: $conversation->id);

        return back();
    }

    public function state(Request $request, Conversation $conversation): RedirectResponse
    {
        abort_unless($conversation->contains($request->user()->id), 404);
        $data = $request->validate(['archived' => ['sometimes', 'required', 'boolean'], 'read_through' => ['sometimes', 'required', 'integer', 'min:1']]);
        DB::transaction(function () use ($request, $conversation, $data): void {
            $locked = Conversation::query()->lockForUpdate()->findOrFail($conversation->id);
            $changes = [];
            if (array_key_exists('archived', $data)) {
                $changes[$locked->archiveColumn($request->user()->id)] = (bool) $data['archived'];
            }
            if (isset($data['read_through'])) {
                abort_unless($locked->messages()->whereKey($data['read_through'])->exists(), 422);
                $column = $locked->readColumn($request->user()->id);
                $changes[$column] = max((int) $locked->getAttribute($column), (int) $data['read_through']);
            }
            // Read/archive changes do not reorder the conversation for the counterpart.
            $locked->timestamps = false;
            $locked->forceFill($changes)->save();
        });
        if (isset($data['read_through'])) {
            $this->messageNotifications($request->user(), $conversation->id)->each->markAsRead();
        }

        return back();
    }

    /** @return array{kind: string, archived: bool, unread: bool, q: string} */
    private function filters(Request $request): array
    {
        $request->validate([
            'kind' => ['nullable', Rule::in(['hiring', 'contracts'])],
            'archived' => ['nullable', 'boolean'],
            'unread' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'list_page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        return ['kind' => $request->string('kind')->value(), 'archived' => $request->boolean('archived'),
            'unread' => $request->boolean('unread'), 'q' => $request->string('q')->trim()->value()];
    }

    /**
     * @param  array{kind: string, archived: bool, unread: bool, q: string}  $filters
     * @return Builder<Conversation>
     */
    private function inbox(int $user, array $filters): Builder
    {
        $query = Conversation::query()->with(['proposal.project', 'client', 'freelancer', 'latestMessage'])
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('client_id', $user)->where('client_archived', $filters['archived']))
                ->orWhere(fn ($q) => $q->where('freelancer_id', $user)->where('freelancer_archived', $filters['archived'])));

        if ($filters['kind'] === 'contracts') {
            $query->whereIn('proposal_id', Contract::query()->select('proposal_id'));
        } elseif ($filters['kind'] === 'hiring') {
            $query->whereNotIn('proposal_id', Contract::query()->select('proposal_id'));
        }

        if ($filters['q'] !== '') {
            $term = '%'.$filters['q'].'%';
            $query->where(fn ($q) => $q->whereHas('proposal.project', fn ($q) => $q->where('title', 'like', $term))
                ->orWhere(fn ($q) => $q->where('client_id', $user)->whereHas('freelancer', fn ($q) => $q->where('name', 'like', $term)))
                ->orWhere(fn ($q) => $q->where('freelancer_id', $user)->whereHas('client', fn ($q) => $q->where('name', 'like', $term))));
        }

        if ($filters['unread']) {
            $query->whereHas('messages', fn ($q) => $q->where('sender_id', '!=', $user)->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('conversations.client_id', $user)->whereColumn('conversation_messages.id', '>', 'conversations.client_read_through'))
                ->orWhere(fn ($q) => $q->where('conversations.freelancer_id', $user)->whereColumn('conversation_messages.id', '>', 'conversations.freelancer_read_through'))));
        }

        return $query->orderByDesc('updated_at')->orderByDesc('id');
    }

    private function lockParticipants(int $client, int $freelancer): void
    {
        User::query()->whereIn('id', [$client, $freelancer])->orderBy('id')->lockForUpdate()->get();
    }

    private function lockWritable(Conversation $conversation): Conversation
    {
        $this->lockParticipants($conversation->client_id, $conversation->freelancer_id);
        Project::query()->lockForUpdate()->findOrFail($conversation->proposal->project_id);
        $proposal = Proposal::query()->lockForUpdate()->findOrFail($conversation->proposal_id);
        abort_unless(HiringAccess::conversationWritable($proposal), 409, __('This hiring conversation is read-only.'));

        return Conversation::query()->lockForUpdate()->findOrFail($conversation->id);
    }

    private function sendMessage(Conversation $conversation, int $sender, string $body, string $token): void
    {
        $existing = $conversation->messages()->where('sender_id', $sender)->where('client_token', $token)->first();
        if ($existing) {
            abort_unless($existing->body === $body, 409, __('This send request was already used for another message.'));

            return;
        }
        $message = new ConversationMessage;
        $message->forceFill(['conversation_id' => $conversation->id, 'sender_id' => $sender, 'body' => $body, 'client_token' => $token])->save();
        $conversation->forceFill(['client_archived' => false, 'freelancer_archived' => false])->touch();
        DB::afterCommit(fn () => $this->announce($conversation, $sender));
    }

    /** The counterpart's open pages update at once; the bell keeps one unread entry per conversation however many messages arrive. */
    private function announce(Conversation $conversation, int $sender): void
    {
        $recipient = User::query()->find($sender === $conversation->client_id ? $conversation->freelancer_id : $conversation->client_id);
        if (! $recipient) {
            return;
        }
        if ($this->messageNotifications($recipient, $conversation->id)->isNotEmpty()) {
            WorkspaceSignal::send($recipient->id, conversation: $conversation->id);

            return;
        }
        $recipient->notify(new WorkspaceEvent('message_received', '/messages/'.$conversation->id, $conversation->proposal->project->title,
            User::query()->whereKey($sender)->value('name'), $conversation->id));
    }

    /**
     * The member's unread bell entries for one conversation.
     *
     * @return Collection<int, DatabaseNotification>
     */
    private function messageNotifications(User $user, int $conversation): Collection
    {
        // The stored JSON is narrowed by text so the query stays portable, then matched exactly.
        return $user->unreadNotifications()->where('data', 'like', '%"kind":"message_received"%')->get()
            ->filter(fn (DatabaseNotification $notification) => ($notification->data['conversation'] ?? null) === $conversation)->values();
    }

    /** @return array<string, mixed> */
    private function summary(Conversation $conversation, int $user): array
    {
        return [
            'id' => $conversation->id, 'project' => $conversation->proposal->project->only(['id', 'title']),
            'proposal_id' => $conversation->proposal_id,
            'contract_id' => Contract::query()->where('proposal_id', $conversation->proposal_id)->value('id'),
            'counterpart' => $user === $conversation->client_id ? $conversation->freelancer->name : $conversation->client->name,
            'preview' => $conversation->latestMessage?->moderated_at === null ? Str::limit($conversation->latestMessage->body ?? '', 120) : '',
            'preview_hidden' => $conversation->latestMessage?->moderated_at !== null,
            'archived' => (bool) $conversation->getAttribute($conversation->archiveColumn($user)),
            'unread' => $conversation->messages()->where('sender_id', '!=', $user)->where('id', '>', $conversation->getAttribute($conversation->readColumn($user)))->count(),
            'updated_at' => $conversation->updated_at,
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\Conversations\HiringAccess;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate(['archived' => ['nullable', 'boolean'], 'page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        $user = $request->user()->id;
        $archived = $request->boolean('archived');
        $query = Conversation::query()->with(['proposal.project', 'client', 'freelancer'])->where(fn ($q) => $q
            ->where(fn ($q) => $q->where('client_id', $user)->where('client_archived', $archived))
            ->orWhere(fn ($q) => $q->where('freelancer_id', $user)->where('freelancer_archived', $archived)));

        return Inertia::render('messages/index', ['conversations' => $query->orderByDesc('updated_at')->orderByDesc('id')->paginate(20)->withQueryString()->through(fn (Conversation $conversation) => $this->summary($conversation, $user)), 'archived' => $archived]);
    }

    public function start(Request $request, Proposal $proposal): RedirectResponse
    {
        abort_unless($proposal->project->user_id === $request->user()->id && $proposal->submitted_at, 404);
        $data = $request->validate(['body' => ['required', 'string', 'max:10000'], 'client_token' => ['required', 'uuid']]);
        $conversation = DB::transaction(function () use ($request, $proposal, $data): Conversation {
            $this->lockParticipants($proposal->project->user_id, $proposal->user_id);
            Project::query()->lockForUpdate()->findOrFail($proposal->project_id);
            $proposal = Proposal::query()->lockForUpdate()->findOrFail($proposal->id);
            abort_unless(HiringAccess::writable($proposal), 409, __('This hiring conversation is read-only.'));
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
        $request->validate(['page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        $user = $request->user()->id;
        $writable = HiringAccess::writable($conversation->proposal);
        $messages = $conversation->messages()->orderByDesc('id')->paginate(30);
        $revisions = DB::table('message_revisions')->whereIn('conversation_message_id', $messages->getCollection()->pluck('id'))->orderBy('version')->get(['conversation_message_id', 'body', 'version', 'created_at'])->groupBy('conversation_message_id');
        $visibleThrough = (int) ($messages->getCollection()->max('id') ?? 0);

        return Inertia::render('messages/show', [
            'conversation' => $this->summary($conversation, $user), 'writable' => $writable, 'visibleThrough' => $visibleThrough,
            'messages' => $messages->through(fn (ConversationMessage $message) => [
                ...$message->only(['id', 'body', 'version', 'created_at', 'edited_at']),
                'mine' => $message->sender_id === $user,
                'can_edit' => $writable && $message->sender_id === $user && $message->created_at->addMinutes(15)->isFuture(),
                'revisions' => ($revisions->get($message->id) ?? collect())->map(fn ($revision) => ['body' => $revision->body, 'version' => $revision->version, 'created_at' => $revision->created_at])->values(),
            ]),
        ]);
    }

    public function send(Request $request, Conversation $conversation): RedirectResponse
    {
        abort_unless($conversation->contains($request->user()->id), 404);
        $data = $request->validate(['body' => ['required', 'string', 'max:10000'], 'client_token' => ['required', 'uuid']]);
        DB::transaction(function () use ($request, $conversation, $data): void {
            $locked = $this->lockWritable($conversation);
            $this->sendMessage($locked, $request->user()->id, $data['body'], $data['client_token']);
        }, 3);

        return to_route('messages.show', $conversation);
    }

    public function edit(Request $request, ConversationMessage $message): RedirectResponse
    {
        abort_unless($message->sender_id === $request->user()->id && $message->conversation->contains($request->user()->id), 404);
        $data = $request->validate(['body' => ['required', 'string', 'max:10000'], 'version' => ['required', 'integer', 'min:1']]);
        DB::transaction(function () use ($message, $data): void {
            $this->lockWritable($message->conversation);
            $locked = ConversationMessage::query()->lockForUpdate()->findOrFail($message->id);
            abort_unless($locked->created_at->addMinutes(15)->isFuture(), 409, __('The message correction window has ended.'));
            abort_unless($locked->version === (int) $data['version'], 409, __('This message changed. Reload before correcting it.'));
            if ($locked->body === $data['body']) {
                return;
            }
            DB::table('message_revisions')->insert(['conversation_message_id' => $locked->id, 'body' => $locked->body, 'version' => $locked->version, 'created_at' => now()]);
            $locked->forceFill(['body' => $data['body'], 'version' => $locked->version + 1, 'edited_at' => now()])->save();
        }, 3);

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

        return back();
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
        abort_unless(HiringAccess::writable($proposal), 409, __('This hiring conversation is read-only.'));

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
    }

    /** @return array<string, mixed> */
    private function summary(Conversation $conversation, int $user): array
    {
        return [
            'id' => $conversation->id, 'project' => $conversation->proposal->project->only(['id', 'title']),
            'proposal_id' => $conversation->proposal_id,
            'counterpart' => $user === $conversation->client_id ? $conversation->freelancer->name : $conversation->client->name,
            'archived' => (bool) $conversation->getAttribute($conversation->archiveColumn($user)),
            'unread' => $conversation->messages()->where('sender_id', '!=', $user)->where('id', '>', $conversation->getAttribute($conversation->readColumn($user)))->count(),
            'updated_at' => $conversation->updated_at,
        ];
    }
}

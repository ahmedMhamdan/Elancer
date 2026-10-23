<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Contract;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Offer;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Plan 032 part 13: a conversation is read when its newest messages are on screen, and the page shows the deal behind it. */
class MessagesReadingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('uploads');
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function member(string $name): User
    {
        return User::factory()->create(['onboarding_completed_at' => now(), 'name' => $name]);
    }

    private function proposal(User $client, User $freelancer, string $title = 'Build an application'): Proposal
    {
        $project = new Project;
        $project->forceFill(['user_id' => $client->id, 'category_id' => Category::firstOrCreate(['categoryname' => 'Development'])->id,
            'title' => $title, 'description' => 'A real project brief.', 'budget_min' => 100, 'budget_max' => 500,
            'status' => 'published', 'published_at' => now()->subDay(), 'application_closes_at' => now()->subHour()])->save();
        $proposal = new Proposal;
        $proposal->forceFill(['project_id' => $project->id, 'user_id' => $freelancer->id, 'status' => 'submitted', 'version' => 1,
            'content' => ['price' => '300.00', 'duration_days' => 7], 'submitted_at' => now()->subHours(2)])->save();

        return $proposal;
    }

    private function start(User $client, Proposal $proposal, string $body = 'Let us discuss the project.'): Conversation
    {
        $this->actingAs($client)->post('/proposals/'.$proposal->id.'/conversation', ['body' => $body, 'client_token' => (string) Str::uuid()])->assertRedirect();

        return Conversation::query()->where('proposal_id', $proposal->id)->firstOrFail();
    }

    private function bell(User $user): int
    {
        return $user->unreadNotifications()->where('data', 'like', '%"kind":"message_received"%')->count();
    }

    public function test_opening_a_conversation_reads_it_and_only_its_bell_entry_without_reordering_it(): void
    {
        $freelancer = $this->member('Fadi Freelancer');
        $first = $this->start($client = $this->member('Clara Client'), $this->proposal($client, $freelancer));
        $second = $this->start($other = $this->member('Omar Other'), $this->proposal($other, $freelancer, 'Design a logo'));
        $this->assertSame(2, $this->bell($freelancer));
        $message = (int) ConversationMessage::query()->where('conversation_id', $first->id)->value('id');
        $this->travel(5)->minutes();

        // The shared counts are taken after the page read the conversation, so they agree with it at once.
        $this->actingAs($freelancer)->get('/messages/'.$first->id)->assertInertia(fn (Assert $page) => $page
            ->where('firstUnread', $message)->where('newMessages', 1)->where('conversation.unread', 0)
            ->where('inbox.unread', 1)->where('notifications.unread', $freelancer->unreadNotifications()->count())
            // The list counts each row in its own query: the newer conversation first, still unread.
            ->where('conversations.data.0.id', $second->id)->where('conversations.data.0.unread', 1)
            ->where('conversations.data.1.id', $first->id)->where('conversations.data.1.unread', 0));
        $this->assertSame(1, $this->bell($freelancer));
        $this->assertSame($message, $first->fresh()->freelancer_read_through);
        // Reading does not move the conversation up the other side's list.
        $this->assertTrue($first->fresh()->updated_at->equalTo($first->updated_at));
        $this->assertSame(1, ConversationMessage::query()->where('conversation_id', $second->id)->count());

        // Nothing new since: no "new messages" line.
        $this->get('/messages/'.$first->id)->assertInertia(fn (Assert $page) => $page->where('firstUnread', null));
    }

    public function test_an_older_page_or_a_hidden_browser_tab_leaves_the_conversation_unread(): void
    {
        $freelancer = $this->member('Fadi Freelancer');
        $conversation = $this->start($client = $this->member('Clara Client'), $this->proposal($client, $freelancer));

        $this->actingAs($freelancer)->get('/messages/'.$conversation->id.'?page=2')->assertInertia(fn (Assert $page) => $page
            ->where('firstUnread', null)->where('conversation.unread', 1));
        $this->get('/messages/'.$conversation->id, ['X-Elancer-Hidden' => '1'])->assertInertia(fn (Assert $page) => $page
            ->where('firstUnread', null)->where('conversation.unread', 1)->where('inbox.unread', 1));
        $this->assertSame(1, $this->bell($freelancer));

        $this->get('/messages/'.$conversation->id)->assertInertia(fn (Assert $page) => $page->where('conversation.unread', 0)->where('inbox.unread', 0));
        $this->assertSame(0, $this->bell($freelancer));
    }

    public function test_answering_reads_the_conversation_for_the_one_who_answers_only(): void
    {
        $freelancer = $this->member('Fadi Freelancer');
        $conversation = $this->start($client = $this->member('Clara Client'), $this->proposal($client, $freelancer));
        $this->actingAs($client)->post('/messages/'.$conversation->id, ['body' => 'One more detail.', 'client_token' => (string) Str::uuid()])->assertRedirect();

        // The freelancer answers without opening the page first.
        $this->actingAs($freelancer)->post('/messages/'.$conversation->id, ['body' => 'Thanks, reading now.', 'client_token' => (string) Str::uuid()])->assertRedirect();
        $reply = (int) ConversationMessage::query()->max('id');
        $this->assertSame($reply, $conversation->fresh()->freelancer_read_through);
        $this->assertSame(0, $this->bell($freelancer));
        $this->get('/messages')->assertInertia(fn (Assert $page) => $page->where('conversations.data.0.unread', 0)
            ->where('conversations.data.0.preview', 'Thanks, reading now.')->where('conversations.data.0.preview_mine', true));

        // The client's side is untouched by it, and nothing says whether the reply was seen (Q86).
        $this->actingAs($client)->get('/messages')->assertInertia(fn (Assert $page) => $page->where('conversations.data.0.unread', 1)
            ->where('conversations.data.0.preview_mine', false)->missing('conversations.data.0.freelancer_read_through'));
        $this->assertSame(1, $this->bell($client));

        // Opening it counts both of the freelancer's new messages, wherever the line falls.
        $this->post('/messages/'.$conversation->id, ['body' => '', 'client_token' => (string) Str::uuid(),
            'files' => [UploadedFile::fake()->create('brief.pdf', 20, 'application/pdf')]])->assertRedirect();
        $this->actingAs($freelancer)->get('/messages')->assertInertia(fn (Assert $page) => $page
            ->where('conversations.data.0.preview_files', 1)->where('conversations.data.0.unread', 1));
    }

    public function test_no_bell_entry_for_a_message_the_recipient_has_already_read(): void
    {
        $freelancer = $this->member('Fadi Freelancer');
        $conversation = $this->start($client = $this->member('Clara Client'), $this->proposal($client, $freelancer));
        $this->actingAs($freelancer)->get('/messages/'.$conversation->id)->assertOk();
        $this->assertSame(0, $this->bell($freelancer));

        // The freelancer's open page read past the next message before its announcement ran (a race, set up here).
        $conversation->forceFill(['freelancer_read_through' => 1_000_000_000])->save();
        $this->actingAs($client)->post('/messages/'.$conversation->id, ['body' => 'Already on screen.', 'client_token' => (string) Str::uuid()])->assertRedirect();
        $this->assertSame(0, $this->bell($freelancer));

        $conversation->forceFill(['freelancer_read_through' => 0])->save();
        $this->post('/messages/'.$conversation->id, ['body' => 'Not yet read.', 'client_token' => (string) Str::uuid()])->assertRedirect();
        $this->assertSame(1, $this->bell($freelancer));
    }

    public function test_reading_up_to_an_older_message_keeps_the_bell_for_a_newer_one(): void
    {
        $freelancer = $this->member('Fadi Freelancer');
        $conversation = $this->start($client = $this->member('Clara Client'), $this->proposal($client, $freelancer));
        $first = (int) ConversationMessage::query()->max('id');
        // The workroom tab drew the first message; the second arrived before its read request.
        $this->post('/messages/'.$conversation->id, ['body' => 'A second message.', 'client_token' => (string) Str::uuid()])->assertRedirect();

        $this->actingAs($freelancer)->patch('/messages/'.$conversation->id.'/state', ['read_through' => $first])->assertRedirect();
        $this->assertSame(1, $this->bell($freelancer));
        $this->patch('/messages/'.$conversation->id.'/state', ['read_through' => (int) ConversationMessage::query()->max('id')])->assertRedirect();
        $this->assertSame(0, $this->bell($freelancer));
    }

    public function test_an_expired_offer_waits_on_nobody(): void
    {
        $freelancer = $this->member('Fadi Freelancer');
        $proposal = $this->proposal($client = $this->member('Clara Client'), $freelancer);
        $conversation = $this->start($client, $proposal);
        $this->post('/proposals/'.$proposal->id.'/offer', ['scope' => 'Build a bilingual application with the agreed account and project workflows.',
            'deliverables' => ['Application source code'], 'amount' => '750.25', 'duration_days' => 14, 'revision_rounds' => 1,
            'proposal_version' => 1, 'client_token' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->travel(73)->hours();

        // Still "pending" in the table until the schedule closes it; the page does not wait on it.
        $this->assertSame('pending', Offer::query()->value('status'));
        $this->get('/messages/'.$conversation->id)->assertInertia(fn (Assert $page) => $page
            ->where('conversation.offer.status', 'expired')->where('conversation.turn', 'yours'));
        $this->actingAs($freelancer)->get('/messages/'.$conversation->id)->assertInertia(fn (Assert $page) => $page
            ->where('conversation.offer.status', 'expired')->where('conversation.turn', null));
        $this->assertSame('pending', Offer::query()->value('status'));
    }

    public function test_the_list_and_header_carry_the_deal_and_whose_move_it_is(): void
    {
        $freelancer = $this->member('Fadi Freelancer');
        $proposal = $this->proposal($client = $this->member('Clara Client'), $freelancer);
        $hiring = $this->start($client, $proposal);
        $this->actingAs($client)->get('/messages/'.$hiring->id)->assertInertia(fn (Assert $page) => $page
            // While hiring is open, sending the final offer is the client's move.
            ->where('conversation.contract_id', null)->where('conversation.status', null)->where('conversation.turn', 'yours')->where('conversation.offer', null)
            ->where('conversation.amount', '300.00')->where('conversation.is_client', true)->where('conversation.proposal_id', $proposal->id));

        $this->post('/proposals/'.$proposal->id.'/offer', ['scope' => 'Build a bilingual application with the agreed account and project workflows.',
            'deliverables' => ['Application source code'], 'amount' => '750.25', 'duration_days' => 14, 'revision_rounds' => 1,
            'proposal_version' => 1, 'client_token' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $offer = Offer::query()->latest('id')->firstOrFail();
        // A pending offer waits on the freelancer.
        $this->get('/messages/'.$hiring->id)->assertInertia(fn (Assert $page) => $page->where('conversation.turn', 'theirs')->where('conversation.offer.status', 'pending'));
        $this->actingAs($freelancer)->get('/messages/'.$hiring->id)->assertInertia(fn (Assert $page) => $page
            ->where('conversation.turn', 'yours')->where('conversation.offer.id', $offer->id)->where('conversation.is_client', false));
        $this->actingAs($freelancer)->patch('/offers/'.Offer::query()->latest('id')->value('id'), ['action' => 'accept', 'version' => 1])->assertSessionHasNoErrors();
        $contract = Contract::query()->latest('id')->firstOrFail();

        // Awaiting payment: the client's move.
        $this->actingAs($client)->get('/messages/'.$hiring->id)->assertInertia(fn (Assert $page) => $page
            ->where('conversation.contract_id', $contract->id)->where('conversation.status', 'awaiting_payment')
            ->where('conversation.turn', 'yours')->where('conversation.amount', '750.25')->where('conversation.due_at', null));
        $this->actingAs($freelancer)->get('/messages?kind=contracts')->assertInertia(fn (Assert $page) => $page
            ->where('conversations.data.0.turn', 'theirs')->where('conversations.data.0.is_client', false)->where('conversations.data.0.avatar', null));

        $contract->forceFill(['status' => 'active', 'delivery_due_at' => now()->addDays(14)])->save();
        $this->get('/messages/'.$hiring->id)->assertInertia(fn (Assert $page) => $page
            ->where('conversation.turn', 'yours')->where('conversation.due_at', fn ($due) => $due !== null));
    }

    public function test_the_details_panel_shows_the_record_the_files_and_a_public_profile_only(): void
    {
        $freelancer = $this->member('Fadi Freelancer');
        $proposal = $this->proposal($client = $this->member('Clara Client'), $freelancer);
        $profile = $freelancer->profile()->create(['headline' => 'Laravel developer', 'bio' => 'I build dependable web applications.']);
        $profile->forceFill(['country' => 'Palestine'])->save();
        $proposal->forceFill(['profile_snapshot' => $profile->publicDetails()])->save();
        $client->profile()->create([])->forceFill(['company' => 'Clara Studio'])->save();
        $conversation = $this->start($client, $proposal);
        $this->actingAs($freelancer)->post('/messages/'.$conversation->id, ['body' => '', 'client_token' => (string) Str::uuid(),
            'files' => [UploadedFile::fake()->create('plan.pdf', 20, 'application/pdf')]])->assertRedirect();
        $this->post('/messages/'.$conversation->id, ['body' => 'Hidden later.', 'client_token' => (string) Str::uuid(),
            'files' => [UploadedFile::fake()->create('hidden.pdf', 20, 'application/pdf')]])->assertRedirect();
        ConversationMessage::query()->where('body', 'Hidden later.')->update(['moderated_at' => now()]);

        // Not yet published: no link to a page that would not open.
        $this->actingAs($client)->get('/messages/'.$conversation->id)->assertInertia(fn (Assert $page) => $page
            ->where('details.profile', null)->where('details.headline', 'Laravel developer')->where('details.country', 'Palestine')
            ->where('details.company', null)->where('details.record.completed', 0)->where('details.record.rating.count', 0)
            ->where('details.files_total', 1)->where('details.files.0.name', 'plan.pdf')->missing('details.files.0.path'));

        $profile->forceFill(['published_at' => now()->subDay()])->save();
        $profile->skillTags()->sync([Skill::query()->orderBy('id')->value('id')]);
        $this->get('/messages/'.$conversation->id)->assertInertia(fn (Assert $page) => $page->where('details.profile', $profile->id));

        // The freelancer sees the client's company and nothing from the freelancer's own profile.
        $this->actingAs($freelancer)->get('/messages/'.$conversation->id)->assertInertia(fn (Assert $page) => $page
            ->where('details.company', 'Clara Studio')->where('details.profile', null)->where('details.headline', null));
    }
}

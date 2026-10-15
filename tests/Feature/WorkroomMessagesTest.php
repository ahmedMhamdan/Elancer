<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Contract;
use App\Models\ConversationMessage;
use App\Models\Offer;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Plan 031 (Q31): the contract's conversation as the workroom's Messages tab, read and written as on the Messages page. */
class WorkroomMessagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('uploads');
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    /** @return array{User, User, Contract} */
    private function contract(): array
    {
        $client = User::factory()->create(['onboarding_completed_at' => now(), 'name' => 'Clara Client']);
        $freelancer = User::factory()->create(['onboarding_completed_at' => now(), 'name' => 'Fadi Freelancer']);
        $project = new Project;
        $project->forceFill(['user_id' => $client->id, 'category_id' => Category::create(['categoryname' => 'Development'])->id,
            'title' => 'Build an application', 'description' => 'A real project brief.', 'budget_min' => 100, 'budget_max' => 500,
            'status' => 'published', 'published_at' => now()->subDay(), 'application_closes_at' => now()->subHour()])->save();
        $proposal = new Proposal;
        $proposal->forceFill(['project_id' => $project->id, 'user_id' => $freelancer->id, 'status' => 'submitted', 'version' => 1,
            'content' => ['price' => '300.00', 'duration_days' => 7], 'submitted_at' => now()->subHours(2)])->save();
        $this->actingAs($client)->post('/proposals/'.$proposal->id.'/conversation', ['body' => 'Let us discuss the project.', 'client_token' => (string) Str::uuid()])->assertRedirect();
        $this->post('/proposals/'.$proposal->id.'/offer', ['scope' => 'Build a bilingual application with the agreed account and project workflows.',
            'deliverables' => ['Application source code'], 'amount' => '750.25', 'duration_days' => 14, 'revision_rounds' => 1,
            'proposal_version' => 1, 'client_token' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->actingAs($freelancer)->patch('/offers/'.Offer::query()->latest('id')->value('id'), ['action' => 'accept', 'version' => 1])->assertSessionHasNoErrors();

        return [$client, $freelancer, Contract::query()->latest('id')->firstOrFail()];
    }

    public function test_both_participants_read_and_write_the_conversation_in_the_workroom(): void
    {
        [$client, $freelancer, $contract] = $this->contract();
        $workroom = '/contracts/'.$contract->id;

        $this->actingAs($freelancer)->get($workroom)->assertInertia(fn (Assert $page) => $page->component('contracts/show')
            ->where('thread.id', $contract->conversation_id)->where('thread.counterpart', 'Clara Client')
            ->where('thread.writable', true)->where('thread.fileLimit', 3)->where('thread.unread', 1)
            ->where('thread.messages.data.0.body', 'Let us discuss the project.')->where('thread.messages.data.0.mine', false));

        // Sent from the tab, a message with a file brings the member back to the tab.
        $this->post('/messages/'.$contract->conversation_id, ['body' => 'Starting today.', 'client_token' => (string) Str::uuid(), 'workroom' => '1',
            'files' => [UploadedFile::fake()->create('plan.pdf', 20, 'application/pdf')]])
            ->assertRedirect(route('contracts.show', ['contract' => $contract->id, 'tab' => 'messages']));
        // The Messages page keeps its own return.
        $this->post('/messages/'.$contract->conversation_id, ['body' => 'From the inbox.', 'client_token' => (string) Str::uuid()])
            ->assertRedirect(route('messages.show', $contract->conversation_id));

        $this->actingAs($client)->get($workroom.'?tab=messages')->assertInertia(fn (Assert $page) => $page
            ->where('thread.counterpart', 'Fadi Freelancer')->where('thread.unread', 2)
            ->where('thread.messages.data.0.body', 'From the inbox.')->where('thread.messages.data.1.files.0.name', 'plan.pdf')
            ->where('thread.messages.data.2.can_edit', true));
        $through = (int) ConversationMessage::query()->max('id');
        $this->patch('/messages/'.$contract->conversation_id.'/state', ['read_through' => $through])->assertRedirect();
        $this->get($workroom)->assertInertia(fn (Assert $page) => $page->where('thread.unread', 0)->where('thread.visibleThrough', $through));

        // Q68: a message moderation hid is a notice here too.
        ConversationMessage::query()->where('body', 'Starting today.')->update(['moderated_at' => now()]);
        $this->get($workroom)->assertInertia(fn (Assert $page) => $page->where('thread.messages.data.1.hidden', true)
            ->where('thread.messages.data.1.body', null)->where('thread.messages.data.1.files', []));

        // Someone outside the contract reads neither the workroom nor its conversation.
        $this->actingAs(User::factory()->create(['onboarding_completed_at' => now()]))->get($workroom)->assertNotFound();
    }

    public function test_older_messages_page_inside_the_tab(): void
    {
        [$client, , $contract] = $this->contract();
        foreach (range(1, 31) as $number) {
            (new ConversationMessage)->forceFill(['conversation_id' => $contract->conversation_id, 'sender_id' => $client->id,
                'body' => 'Update '.$number, 'client_token' => (string) Str::uuid()])->save();
        }

        $this->actingAs($client)->get('/contracts/'.$contract->id)->assertInertia(fn (Assert $page) => $page
            ->has('thread.messages.data', 30)->where('thread.messages.last_page', 2)
            ->where('thread.messages.next_page_url', fn (string $url) => str_contains($url, 'messages_page=2') && str_contains($url, 'tab=messages')));
        $this->get('/contracts/'.$contract->id.'?tab=messages&messages_page=2')->assertInertia(fn (Assert $page) => $page
            ->has('thread.messages.data', 2)->where('thread.messages.data.1.body', 'Let us discuss the project.'));
    }
}

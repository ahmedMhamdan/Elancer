<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HiringConversationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, User, Proposal} */
    private function participants(): array
    {
        $client = User::factory()->create(['onboarding_completed_at' => now()]);
        $freelancer = User::factory()->create(['onboarding_completed_at' => now()]);
        $project = new Project;
        $project->forceFill(['user_id' => $client->id, 'category_id' => Category::create(['categoryname' => 'Development'])->id,
            'title' => 'Build an application', 'description' => 'A real project brief.', 'budget_min' => 100, 'budget_max' => 500,
            'status' => 'published', 'published_at' => now()->subDay(), 'application_closes_at' => now()->subHour()])->save();
        $proposal = new Proposal;
        $proposal->forceFill(['project_id' => $project->id, 'user_id' => $freelancer->id, 'status' => 'submitted', 'submitted_at' => now()->subHours(2)])->save();

        return [$client, $freelancer, $proposal];
    }

    private function start(User $client, Proposal $proposal): Conversation
    {
        $this->actingAs($client)->post('/proposals/'.$proposal->id.'/conversation', ['body' => 'Let us discuss the project.', 'client_token' => (string) Str::uuid()])->assertRedirect();

        return Conversation::query()->where('proposal_id', $proposal->id)->firstOrFail();
    }

    public function test_only_client_can_start_after_submission_and_other_accounts_cannot_read_or_write(): void
    {
        [$client, $freelancer, $proposal] = $this->participants();
        $payload = ['body' => 'Hello', 'client_token' => (string) Str::uuid()];
        $this->actingAs($freelancer)->post('/proposals/'.$proposal->id.'/conversation', $payload)->assertNotFound();
        $proposal->forceFill(['status' => 'draft', 'submitted_at' => null])->save();
        $this->actingAs($client)->post('/proposals/'.$proposal->id.'/conversation', $payload)->assertNotFound();
        $proposal->forceFill(['status' => 'submitted', 'submitted_at' => now()])->save();
        $conversation = $this->start($client, $proposal);
        $this->post('/proposals/'.$proposal->id.'/conversation', $payload)->assertRedirect();
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('conversation_messages', 1);
        $other = User::factory()->create(['onboarding_completed_at' => now(), 'is_admin' => true]);
        $this->actingAs($other)->get('/messages/'.$conversation->id)->assertNotFound();
        $this->post('/messages/'.$conversation->id, $payload)->assertNotFound();
        $this->patch('/messages/'.$conversation->id.'/state', ['archived' => true])->assertNotFound();
        $this->get('/messages')->assertInertia(fn (Assert $page) => $page->has('conversations.data', 0));
        $this->actingAs($freelancer)->get('/messages/'.$conversation->id)->assertOk();
    }

    public function test_sends_are_idempotent_and_read_archive_state_never_leaks_to_counterpart(): void
    {
        [$client, $freelancer, $proposal] = $this->participants();
        $conversation = $this->start($client, $proposal);
        $payload = ['body' => 'A reply from the freelancer.', 'client_token' => (string) Str::uuid()];
        $this->actingAs($freelancer)->post('/messages/'.$conversation->id, $payload)->assertRedirect();
        $this->post('/messages/'.$conversation->id, $payload)->assertRedirect();
        $this->post('/messages/'.$conversation->id, array_merge($payload, ['body' => 'Changed request']))->assertConflict();
        $this->assertDatabaseCount('conversation_messages', 2);
        $latest = ConversationMessage::query()->max('id');
        $this->actingAs($client)->get('/messages/'.$conversation->id)->assertInertia(fn (Assert $page) => $page->where('conversation.unread', 1)->missing('conversation.freelancer_read_through')->missing('conversation.freelancer_archived')->missing('messages.data.0.client_token'));
        $this->patch('/messages/'.$conversation->id.'/state', ['archived' => true, 'read_through' => $latest])->assertRedirect();
        $this->get('/messages')->assertInertia(fn (Assert $page) => $page->has('conversations.data', 0));
        $this->get('/messages?archived=1')->assertInertia(fn (Assert $page) => $page->where('conversations.data.0.unread', 0));
        $this->actingAs($freelancer)->get('/messages/'.$conversation->id)->assertInertia(fn (Assert $page) => $page->where('conversation.archived', false)->missing('conversation.client_read_through')->missing('conversation.client_archived'));
        $this->post('/messages/'.$conversation->id, ['body' => 'Another reply', 'client_token' => (string) Str::uuid()])->assertRedirect();
        $this->assertFalse($conversation->fresh()->client_archived);
    }

    public function test_corrections_keep_history_and_expire_from_original_send_time(): void
    {
        $this->freezeTime();
        [$client, $freelancer, $proposal] = $this->participants();
        $conversation = $this->start($client, $proposal);
        $message = ConversationMessage::query()->firstOrFail();
        $this->actingAs($freelancer)->patch('/messages/items/'.$message->id, ['body' => 'Tampered', 'version' => 1])->assertNotFound();
        $this->travel(14)->minutes();
        $this->actingAs($client)->patch('/messages/items/'.$message->id, ['body' => 'Corrected message', 'version' => 1])->assertRedirect();
        $this->patch('/messages/items/'.$message->id, ['body' => 'Stale correction', 'version' => 1])->assertConflict();
        $this->assertSame('Let us discuss the project.', DB::table('message_revisions')->value('body'));
        $this->travel(1)->minutes();
        $this->patch('/messages/items/'.$message->id, ['body' => 'Too late', 'version' => 2])->assertConflict();
        $this->assertSame('Corrected message', $message->fresh()->body);
        $this->actingAs($freelancer)->get('/messages/'.$conversation->id)->assertInertia(fn (Assert $page) => $page->where('messages.data.0.revisions.0.body', 'Let us discuss the project.'));
        $this->delete('/messages/items/'.$message->id)->assertStatus(405);
    }

    public function test_decline_blocks_replies_and_corrections_and_reopening_restores_permissions(): void
    {
        [$client, $freelancer, $proposal] = $this->participants();
        $conversation = $this->start($client, $proposal);
        $message = ConversationMessage::query()->firstOrFail();
        $proposal->forceFill(['status' => 'declined'])->save();
        $this->actingAs($freelancer)->post('/messages/'.$conversation->id, ['body' => 'Reply', 'client_token' => (string) Str::uuid()])->assertConflict();
        $this->actingAs($client)->patch('/messages/items/'.$message->id, ['body' => 'Correction', 'version' => 1])->assertConflict();
        $proposal->forceFill(['status' => 'reopened'])->save();
        $this->actingAs($freelancer)->post('/messages/'.$conversation->id, ['body' => 'Reply', 'client_token' => (string) Str::uuid()])->assertRedirect();
        $proposal->project->forceFill(['status' => 'closed'])->save();
        $this->post('/messages/'.$conversation->id, ['body' => 'Closed reply', 'client_token' => (string) Str::uuid()])->assertConflict();
        $this->get('/messages/'.$conversation->id)->assertInertia(fn (Assert $page) => $page->where('writable', false));
    }

    public function test_blocking_and_suspension_preserve_read_access_but_stop_hiring_messages(): void
    {
        [$client, $freelancer, $proposal] = $this->participants();
        $conversation = $this->start($client, $proposal);
        $this->actingAs($freelancer)->post('/messages/'.$conversation->id.'/block')->assertRedirect();
        $this->actingAs($client)->post('/messages/'.$conversation->id, ['body' => 'Blocked', 'client_token' => (string) Str::uuid()])->assertConflict();
        $this->get('/messages/'.$conversation->id)->assertOk();
        DB::table('user_blocks')->delete();
        $freelancer->forceFill(['status' => 'suspended'])->save();
        $this->post('/messages/'.$conversation->id, ['body' => 'Suspended counterpart', 'client_token' => (string) Str::uuid()])->assertConflict();
        $this->actingAs($freelancer)->get('/messages/'.$conversation->id)->assertOk();
        $this->assertDatabaseCount('conversation_messages', 1);
    }
}

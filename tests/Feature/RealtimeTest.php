<?php

namespace Tests\Feature;

use App\Events\WorkspaceSignal;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use App\Notifications\WorkspaceEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RealtimeTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, User, Conversation} */
    private function conversation(): array
    {
        $client = User::factory()->create(['onboarding_completed_at' => now()]);
        $freelancer = User::factory()->create(['onboarding_completed_at' => now()]);
        $project = new Project;
        $project->forceFill(['user_id' => $client->id, 'category_id' => Category::create(['categoryname' => 'Development'])->id,
            'title' => 'Build an application', 'description' => 'A real project brief.', 'budget_min' => 100, 'budget_max' => 500,
            'status' => 'published', 'published_at' => now()->subDay(), 'application_closes_at' => now()->subHour()])->save();
        $proposal = new Proposal;
        $proposal->forceFill(['project_id' => $project->id, 'user_id' => $freelancer->id, 'status' => 'submitted', 'submitted_at' => now()->subHours(2)])->save();
        $this->actingAs($client)->post('/proposals/'.$proposal->id.'/conversation', ['body' => 'Let us discuss the project.', 'client_token' => (string) Str::uuid()])->assertRedirect();

        return [$client, $freelancer, Conversation::query()->firstOrFail()];
    }

    private function useReverb(int $port = 8080): void
    {
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb' => ['driver' => 'reverb', 'key' => 'public-key', 'secret' => 'private-secret',
            'app_id' => 'elancer', 'options' => ['host' => '127.0.0.1', 'port' => $port, 'scheme' => 'http', 'useTLS' => false],
            'client_options' => ['connect_timeout' => 1, 'timeout' => 1]]]);
        // Channels were registered on the test suite's null broadcaster at boot; register them on this one too.
        require base_path('routes/channels.php');
    }

    public function test_a_member_can_listen_only_to_their_own_channel(): void
    {
        $member = User::factory()->create(['onboarding_completed_at' => now()]);
        $other = User::factory()->create(['onboarding_completed_at' => now(), 'is_admin' => true]);
        $this->actingAs($member)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('realtime', null));
        $this->useReverb();

        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('realtime.key', 'public-key')->where('realtime.port', 8080)->missing('realtime.secret'));
        $this->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-workspace.'.$member->id])->assertOk()->assertJsonStructure(['auth']);
        $this->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-workspace.'.$other->id])->assertForbidden();
        $this->actingAs($other)->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-workspace.'.$member->id])->assertForbidden();
    }

    public function test_messages_signal_only_the_counterpart_and_keep_one_unread_bell_entry(): void
    {
        [$client, $freelancer, $conversation] = $this->conversation();
        Event::fake([WorkspaceSignal::class]);
        $this->actingAs($client)->post('/messages/'.$conversation->id, $second = ['body' => 'A second message.', 'client_token' => (string) Str::uuid()])->assertRedirect();
        // A retried send is the same message, not another signal.
        $this->post('/messages/'.$conversation->id, $second)->assertRedirect();

        Event::assertDispatchedTimes(WorkspaceSignal::class, 1);
        Event::assertDispatched(fn (WorkspaceSignal $signal) => $signal->broadcastOn()->name === 'private-workspace.'.$freelancer->id
            && $signal->conversation === $conversation->id && $signal->notification === null && array_keys(get_object_vars($signal)) === ['notification', 'conversation']);
        $this->assertSame(0, $client->notifications()->count());
        $this->assertSame(['message_received'], $freelancer->unreadNotifications()->get()->map(fn ($item) => $item->data['kind'])->all());
        $this->actingAs($freelancer)->getJson('/notifications')->assertJsonPath('notifications.0.actor', $client->name)
            ->assertJsonPath('notifications.0.title', 'Build an application')->assertJsonMissingPath('notifications.0.conversation');

        $this->patch('/messages/'.$conversation->id.'/state', ['read_through' => ConversationMessage::query()->max('id')])->assertRedirect();
        $this->assertSame(0, $freelancer->unreadNotifications()->count());
        $this->actingAs($client)->post('/messages/'.$conversation->id, ['body' => 'After it was read.', 'client_token' => (string) Str::uuid()])->assertRedirect();
        $this->assertSame(1, $freelancer->unreadNotifications()->count());
    }

    public function test_every_stored_notification_signals_its_recipient(): void
    {
        $recipient = User::factory()->create();
        Event::fake([WorkspaceSignal::class]);
        $recipient->notify(new WorkspaceEvent('offer_received', '/offers/7', 'Private project', 'Client name'));

        $stored = $recipient->notifications()->firstOrFail();
        Event::assertDispatched(fn (WorkspaceSignal $signal) => $signal->broadcastOn()->name === 'private-workspace.'.$recipient->id
            && $signal->notification === $stored->id && $signal->conversation === null);
    }

    public function test_an_unreachable_broadcaster_never_fails_the_action(): void
    {
        [$client, $freelancer, $conversation] = $this->conversation();
        // Nothing listens on this port, so every signal fails to send.
        $this->useReverb(9);

        $this->actingAs($freelancer)->post('/messages/'.$conversation->id, ['body' => 'Still delivered.', 'client_token' => (string) Str::uuid()])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('conversation_messages', ['body' => 'Still delivered.']);
        $this->assertSame(1, $client->unreadNotifications()->count());
    }
}

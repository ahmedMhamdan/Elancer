<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Offer;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use App\Notifications\WorkspaceEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_hiring_events_reach_only_their_intended_recipient(): void
    {
        $client = User::factory()->create(['onboarding_completed_at' => now()]);
        $freelancer = User::factory()->create(['onboarding_completed_at' => now()]);
        $project = new Project;
        $project->forceFill(['user_id' => $client->id, 'category_id' => Category::create(['categoryname' => 'Development'])->id,
            'title' => 'Build an application', 'description' => 'A real project brief.', 'budget_min' => 100, 'budget_max' => 500,
            'status' => 'published', 'published_at' => now()->subDay(), 'application_closes_at' => now()->subHour()])->save();
        $proposal = new Proposal;
        $proposal->forceFill(['project_id' => $project->id, 'user_id' => $freelancer->id, 'status' => 'submitted', 'version' => 1,
            'content' => ['price' => '300.00', 'duration_days' => 7], 'submitted_at' => now()->subHours(2)])->save();
        $payload = ['scope' => 'Build a bilingual application with the agreed account and project workflows.', 'deliverables' => ['Application source code'],
            'amount' => '750.25', 'duration_days' => 14, 'revision_rounds' => 2, 'proposal_version' => 1, 'client_token' => (string) Str::uuid()];
        $this->actingAs($client)->post('/proposals/'.$proposal->id.'/offer', $payload)->assertSessionHasNoErrors();
        // A retried send is the same offer, not a second notification.
        $this->post('/proposals/'.$proposal->id.'/offer', $payload)->assertSessionHasNoErrors();
        $offer = Offer::query()->firstOrFail();
        $this->assertSame(0, $client->notifications()->count());
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('notifications.unread', 0));

        $this->actingAs($freelancer)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('notifications.unread', 1));
        $this->getJson('/notifications')->assertOk()->assertJsonPath('unread', 1)->assertJsonCount(1, 'notifications')
            ->assertJsonPath('notifications.0.kind', 'offer_received')->assertJsonPath('notifications.0.actor', $client->name)
            ->assertJsonPath('notifications.0.title', 'Build an application')->assertJsonMissingPath('notifications.0.href');
        $this->patch('/offers/'.$offer->id, ['action' => 'accept', 'version' => 1])->assertSessionHasNoErrors();
        $this->assertSame(['offer_accepted'], $client->notifications()->get()->map(fn ($item) => $item->data['kind'])->all());
        $this->assertSame(1, $freelancer->notifications()->count());
    }

    public function test_only_the_recipient_can_open_or_clear_a_notification(): void
    {
        $recipient = User::factory()->create(['onboarding_completed_at' => now()]);
        $other = User::factory()->create(['onboarding_completed_at' => now(), 'is_admin' => true]);
        $recipient->notify(new WorkspaceEvent('offer_received', '/offers/7', 'Private project', 'Client name'));
        $recipient->notify(new WorkspaceEvent('offer_received', '//evil.example/offers', 'Other project'));
        [$unsafe, $first] = $recipient->notifications()->get()->sortBy(fn ($item) => $item->data['href'])->values()->all();

        $this->actingAs($other)->getJson('/notifications')->assertJsonPath('unread', 0)->assertJsonCount(0, 'notifications');
        $this->patch('/notifications/'.$first->id)->assertNotFound();
        $this->post('/notifications/read')->assertRedirect();
        $this->assertSame(2, $recipient->unreadNotifications()->count());

        $this->actingAs($recipient)->patch('/notifications/'.$first->id)->assertRedirect('/offers/7');
        $this->assertNotNull($first->fresh()->read_at);
        $this->patch('/notifications/'.$unsafe->id)->assertRedirect(route('dashboard'));
        $recipient->notify(new WorkspaceEvent('payment_verified', '/contracts/1'));
        $this->post('/notifications/read')->assertRedirect();
        $this->assertSame(0, $recipient->unreadNotifications()->count());
    }

    public function test_a_signed_out_photo_request_does_not_become_the_login_destination(): void
    {
        $this->get('/account/profile-photo')->assertUnauthorized()->assertSessionMissing('url.intended');
        $this->get('/finance')->assertRedirect('/login')->assertSessionHas('url.intended');
    }
}

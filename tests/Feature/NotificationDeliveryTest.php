<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\WorkspaceEvent;
use App\Notifications\WorkspaceEventMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_notifications_page_lists_only_the_members_own_events(): void
    {
        $member = User::factory()->create(['onboarding_completed_at' => now()]);
        $other = User::factory()->create(['onboarding_completed_at' => now()]);
        foreach (range(1, 22) as $number) {
            $member->notify(new WorkspaceEvent('proposal_received', '/proposals/'.$number, 'Project '.$number, 'Sara'));
        }
        $other->notify(new WorkspaceEvent('proposal_received', '/proposals/99', 'Private project', 'Omar'));
        $this->get('/notifications')->assertRedirect('/login');
        $this->actingAs($member)->get('/notifications')->assertInertia(fn (Assert $page) => $page->component('notifications/index')
            ->has('items.data', 20)->where('items.total', 22)->where('items.data.0.read', false)->missing('items.data.0.href'));
        $this->get('/notifications?page=2')->assertInertia(fn (Assert $page) => $page->has('items.data', 2));
        // The bell still receives JSON from the same address.
        $this->getJson('/notifications')->assertOk()->assertJsonCount(20, 'notifications')->assertJsonPath('unread', 22);
        $this->actingAs($other)->get('/notifications')->assertInertia(fn (Assert $page) => $page->has('items.data', 1)->where('items.data.0.title', 'Private project'));
    }

    public function test_email_follows_the_category_preference(): void
    {
        $sent = [];
        Event::listen(function (NotificationSent $event) use (&$sent): void {
            if ($event->notification instanceof WorkspaceEventMail) {
                $sent[] = $event->notifiable->id;
            }
        });
        $member = User::factory()->create(['onboarding_completed_at' => now()]);
        $this->actingAs($member)->get('/settings/notifications')->assertInertia(fn (Assert $page) => $page
            ->where('preferences', ['contracts' => true, 'hiring' => false, 'messages' => false]));
        // Defaults: contract actions are emailed; hiring and message updates are not.
        $member->notify(new WorkspaceEvent('delivery_submitted', '/contracts/1', 'Project', 'Sara'));
        $member->notify(new WorkspaceEvent('proposal_received', '/proposals/1', 'Project', 'Sara'));
        $member->notify(new WorkspaceEvent('message_received', '/messages/1', 'Project', 'Sara', 1));
        $this->assertCount(1, $sent);
        $this->assertSame(3, $member->notifications()->count());

        $this->patch('/settings/notifications', ['contracts' => 'maybe'])->assertSessionHasErrors(['contracts', 'hiring', 'messages']);
        $this->patch('/settings/notifications', ['contracts' => false, 'hiring' => true, 'messages' => true])->assertSessionHasNoErrors();
        $member->refresh();
        $member->notify(new WorkspaceEvent('delivery_submitted', '/contracts/1', 'Project', 'Sara'));
        $this->assertCount(1, $sent);
        $member->notify(new WorkspaceEvent('proposal_received', '/proposals/1', 'Project', 'Sara'));
        $member->notify(new WorkspaceEvent('message_received', '/messages/1', 'Project', 'Sara', 1));
        $this->assertCount(3, $sent);
        // The bell is never affected by an email preference, and an unverified address gets no email.
        $this->assertSame(6, $member->notifications()->count());
        $unverified = User::factory()->unverified()->create();
        $unverified->notify(new WorkspaceEvent('delivery_submitted', '/contracts/1', 'Project', 'Sara'));
        $this->assertCount(3, $sent);
        $this->assertSame(1, $unverified->notifications()->count());
    }

    public function test_the_email_uses_the_recipients_language_and_a_local_link(): void
    {
        $member = User::factory()->create(['locale' => 'ar']);
        app()->setLocale('ar');
        $mail = (new WorkspaceEventMail('delivery_submitted', '/contracts/7', 'Build an application', 'Sara'))->toMail($member);
        $this->assertSame('إيلانسر: Build an application', $mail->subject);
        $this->assertSame('Sara سلّم العمل في مشروع Build an application', $mail->introLines[0]);
        $this->assertSame(url('/contracts/7'), $mail->actionUrl);
        $this->assertSame('فتح في إيلانسر', $mail->actionText);
        app()->setLocale('en');
        $this->assertSame('Sara submitted a delivery for Build an application', (new WorkspaceEventMail('delivery_submitted', '/contracts/7', 'Build an application', 'Sara'))->toMail($member)->introLines[0]);
    }
}

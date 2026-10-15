<?php

namespace Tests\Feature;

use App\Jobs\SendMessageDigest;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use App\Notifications\WorkspaceEventMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Plan 031 (plan 003 section 15): one email per unread conversation, 15 minutes on, at most once an hour, none once read. */
class MessageDigestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        // Whole seconds, as the database keeps them.
        $this->freezeSecond();
    }

    /** @return array{User, User, Conversation} */
    private function conversation(bool $wantsEmail = true): array
    {
        $client = User::factory()->create(['onboarding_completed_at' => now(), 'name' => 'Clara Client', 'email' => 'clara@elancer.app']);
        $freelancer = User::factory()->create(['onboarding_completed_at' => now(), 'email' => 'fadi@elancer.app',
            'email_preferences' => ['contracts' => true, 'hiring' => false, 'messages' => $wantsEmail]]);
        $project = new Project;
        $project->forceFill(['user_id' => $client->id, 'category_id' => Category::create(['categoryname' => 'Development'])->id,
            'title' => 'Build an application', 'description' => 'A real project brief.', 'budget_min' => 100, 'budget_max' => 500,
            'status' => 'published', 'published_at' => now()->subDay(), 'application_closes_at' => now()->subHour()])->save();
        $proposal = new Proposal;
        $proposal->forceFill(['project_id' => $project->id, 'user_id' => $freelancer->id, 'status' => 'submitted', 'submitted_at' => now()->subHours(2)])->save();
        $this->actingAs($client)->post('/proposals/'.$proposal->id.'/conversation', ['body' => 'A private first message.', 'client_token' => (string) Str::uuid()])->assertRedirect();

        return [$client, $freelancer, Conversation::query()->firstOrFail()];
    }

    private function send(User $sender, Conversation $conversation, string $body): void
    {
        $this->actingAs($sender)->post('/messages/'.$conversation->id, ['body' => $body, 'client_token' => (string) Str::uuid()])->assertRedirect();
    }

    /** Runs the check the queue holds, as the worker would when its time comes. */
    private function runDigest(): void
    {
        $jobs = Queue::pushed(SendMessageDigest::class);
        $this->assertNotEmpty($jobs);
        $job = $jobs->last();
        $this->travelTo($job->delay);
        $job->handle();
    }

    public function test_one_email_stands_for_every_message_sent_meanwhile_and_names_no_text(): void
    {
        Queue::fake();
        [$client, $freelancer, $conversation] = $this->conversation();
        $this->send($client, $conversation, 'A second private message.');
        $this->send($client, $conversation, 'A third private message.');
        // One bell entry, so one check, 15 minutes after the first message.
        Queue::assertPushed(SendMessageDigest::class, 1);
        Queue::assertPushed(SendMessageDigest::class, fn (SendMessageDigest $job) => $job->recipient === $freelancer->id
            && $job->conversation === $conversation->id && $job->delay->equalTo(now()->addMinutes(15)));
        $before = $conversation->fresh()->updated_at;

        Notification::fake();
        $this->runDigest();
        Notification::assertSentTo($freelancer, WorkspaceEventMail::class, function (WorkspaceEventMail $mail) use ($freelancer) {
            $message = $mail->toMail($freelancer);
            $text = implode(' ', $message->introLines);
            $this->assertSame('Clara Client sent you 3 new messages about Build an application', $text);
            $this->assertStringNotContainsString('private', implode(' ', [$message->subject, ...$message->introLines, ...$message->outroLines]));
            $this->assertSame(url('/messages/'.Conversation::query()->value('id')), $message->actionUrl);

            return true;
        });
        Notification::assertNotSentTo($client, WorkspaceEventMail::class);
        $conversation->refresh();
        $this->assertTrue($conversation->freelancer_emailed_at->equalTo(now()));
        $this->assertTrue($conversation->updated_at->equalTo($before), 'An email does not reorder the inbox.');
    }

    public function test_reading_cancels_the_email_and_one_an_hour_is_the_most(): void
    {
        Queue::fake();
        [$client, $freelancer, $conversation] = $this->conversation();
        // The bell needs the real sender; only the emails are caught.
        $real = Notification::getFacadeRoot();
        Notification::fake();
        $this->runDigest();
        Notification::assertSentToTimes($freelancer, WorkspaceEventMail::class, 1);

        // Read, then a new message: a new check, which waits for the hour since the last email.
        $this->actingAs($freelancer)->patch('/messages/'.$conversation->id.'/state', ['read_through' => ConversationMessage::query()->max('id')])->assertRedirect();
        $emailedAt = now();
        $this->travel(5)->minutes();
        Notification::swap($real);
        $this->send($client, $conversation, 'Are you there?');
        Queue::assertPushed(SendMessageDigest::class, 2);
        Notification::fake();
        $this->runDigest();
        Notification::assertNothingSent();
        Queue::assertPushed(SendMessageDigest::class, fn (SendMessageDigest $job) => $job->delay->equalTo($emailedAt->addHour()));
        $this->runDigest();
        Notification::assertSentToTimes($freelancer, WorkspaceEventMail::class, 1);

        // Read before the check runs: nothing is sent.
        $this->actingAs($freelancer)->patch('/messages/'.$conversation->id.'/state', ['read_through' => ConversationMessage::query()->max('id')])->assertRedirect();
        Notification::swap($real);
        $this->send($client, $conversation, 'One more.');
        Notification::fake();
        $this->actingAs($freelancer)->patch('/messages/'.$conversation->id.'/state', ['read_through' => ConversationMessage::query()->max('id')])->assertRedirect();
        $this->runDigest();
        Notification::assertNothingSent();
    }

    public function test_no_check_is_queued_for_a_member_who_does_not_want_message_email(): void
    {
        Queue::fake();
        [$client, $freelancer, $conversation] = $this->conversation(wantsEmail: false);
        $this->send($client, $conversation, 'Hello again.');
        Queue::assertNotPushed(SendMessageDigest::class);
        $this->assertSame(1, $freelancer->unreadNotifications()->count(), 'The bell is unaffected.');
    }
}

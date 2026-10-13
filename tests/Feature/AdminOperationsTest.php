<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Plan 026: super administrators see failed background work, sanitized, and retry or dismiss one piece at a time. */
class AdminOperationsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(bool $super): User
    {
        return User::factory()->withTwoFactor()->create(['is_admin' => true, 'is_super_admin' => $super, 'onboarding_completed_at' => now()]);
    }

    /** Signs in with the current-session two-factor proof the admin gate expects. */
    private function signIn(User $user): void
    {
        $this->actingAs($user)->withSession(['admin.two_factor_proof' => $user->id.':'.hash('sha256', (string) $user->two_factor_secret)]);
    }

    /** A failure as the queue stores it. The command inside the payload is what must never reach a page. */
    private function failure(string $name, string $exception, string $queue = 'default'): int
    {
        return (int) DB::table('failed_jobs')->insertGetId(['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => $queue,
            'payload' => json_encode(['uuid' => (string) Str::uuid(), 'displayName' => $name, 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call', 'maxTries' => 3, 'attempts' => 3,
                'data' => ['commandName' => $name, 'private' => 'PAYLOAD-MARKER member@private.example the text of a message']]),
            'exception' => $exception, 'failed_at' => now()]);
    }

    public function test_only_super_administrators_past_two_factor_see_failed_work(): void
    {
        $id = $this->failure('App\\Notifications\\WorkspaceEventMail', 'RuntimeException: It failed');
        $this->get('/admin/operations')->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['onboarding_completed_at' => now()]))->get('/admin/operations')->assertForbidden();
        $this->signIn($this->admin(false));
        $this->get('/admin/operations')->assertForbidden();
        $this->post('/admin/operations/'.$id.'/retry')->assertForbidden();
        $this->delete('/admin/operations/'.$id)->assertForbidden();

        $super = $this->admin(true);
        $this->actingAs($super)->get('/admin/operations')->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/categories/security'));
        $this->post('/admin/operations/'.$id.'/retry')->assertForbidden();
        $this->delete('/admin/operations/'.$id)->assertForbidden();
        $this->assertSame(1, DB::table('failed_jobs')->count());
        $this->assertSame(0, DB::table('jobs')->count());

        $this->signIn($super);
        $this->get('/admin/operations')->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/operations/index')->has('failures.data', 1));
    }

    public function test_the_list_names_the_work_and_one_sanitized_line_and_never_the_payload(): void
    {
        $this->failure('App\\Jobs\\Unknown', 'A plain line without a class');
        $this->failure('Illuminate\\Notifications\\Events\\BroadcastNotificationCreated', "Pusher\\ApiErrorException: cURL error 28: Operation timed out in C:\\app\\vendor\\pusher\\Pusher.php:533\nStack trace:\n#0 secret frame");
        $this->failure('App\\Notifications\\WorkspaceEventMail', 'Illuminate\\Database\\QueryException: SQLSTATE[23505]: Unique violation: 7 ERROR: duplicate key (Connection: pgsql, SQL: insert into "conversation_messages" ("body") values (A private message body))');
        $this->failure('App\\Notifications\\WorkspaceEventMail', 'Symfony\\Component\\Mailer\\Exception\\TransportException: Connection could not be established with host "smtp-relay.example:2525" for sender@private.example '
            .'using password=hunter2secret and key: abcdef, token '.str_repeat('Ab3', 14).' in /var/www/html/vendor/symfony/mailer/Transport/Smtp/SmtpTransport.php:154'."\nStack trace:\n#0 /var/www/html/secret/frame.php(12)", 'mail');
        DB::table('jobs')->insert([['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => 1790000000, 'created_at' => 1790000000],
            ['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => 1780000000, 'created_at' => 1780000000]]);

        $this->signIn($this->admin(true));
        $response = $this->get('/admin/operations')->assertOk()->assertInertia(fn (Assert $page) => $page->has('failures.data', 4)
            // Newest first.
            ->where('failures.data.0.kind', 'email')->where('failures.data.0.name', 'WorkspaceEventMail')->where('failures.data.0.queue', 'mail')
            ->where('failures.data.0.error', 'TransportException: Connection could not be established with host "smtp-relay.example:2525" for [address] using password [hidden] and key [hidden] token [hidden]')
            ->where('failures.data.1.kind', 'email')->where('failures.data.1.error', 'QueryException: SQLSTATE[23505]: Unique violation: 7 ERROR: duplicate key')
            ->where('failures.data.2.kind', 'live')->where('failures.data.2.name', 'BroadcastNotificationCreated')->where('failures.data.2.error', 'ApiErrorException: cURL error 28: Operation timed out')
            ->where('failures.data.3.kind', 'other')->where('failures.data.3.name', 'Unknown')->where('failures.data.3.error', 'A plain line without a class')
            ->where('queue.waiting', 2)->where('queue.failed', 4)->where('queue.oldest', fn (string $moment) => str_starts_with($moment, '2026-05-28'))
            ->missing('failures.data.0.payload')->missing('failures.data.0.exception')->missing('failures.data.0.uuid'));
        // Nothing private anywhere in what the page was sent, whatever field it might have hidden in.
        $sent = (string) json_encode($response->viewData('page')['props']['failures']);
        foreach (['PAYLOAD-MARKER', 'private.example', 'hunter2', 'A private message body', 'Stack trace', 'secret', 'vendor', 'abcdef', 'Ab3Ab3'] as $private) {
            $this->assertStringNotContainsString($private, $sent);
        }
    }

    public function test_retry_puts_one_failure_back_on_the_queue_and_dismiss_removes_one(): void
    {
        $first = $this->failure('App\\Notifications\\WorkspaceEventMail', 'RuntimeException: It failed', 'mail');
        $second = $this->failure('Illuminate\\Notifications\\Events\\BroadcastNotificationCreated', 'RuntimeException: It failed');
        $super = $this->admin(true);
        $this->signIn($super);

        $this->post('/admin/operations/'.$first.'/retry')->assertStatus(303)->assertSessionHas('operations_notice', 'retried');
        $this->assertSame([$second], DB::table('failed_jobs')->pluck('id')->map(fn ($id) => (int) $id)->all());
        $waiting = DB::table('jobs')->get();
        $this->assertCount(1, $waiting);
        $this->assertSame('mail', $waiting[0]->queue);
        $this->assertSame(0, json_decode($waiting[0]->payload, true)['attempts']);

        $this->delete('/admin/operations/'.$second)->assertStatus(303)->assertSessionHas('operations_notice', 'dismissed');
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertCount(1, DB::table('jobs')->get());

        // Gone is gone: a second press on either finds nothing.
        $this->post('/admin/operations/'.$first.'/retry')->assertNotFound();
        $this->delete('/admin/operations/'.$second)->assertNotFound();
        $this->assertSame([['job_retried', $first, 'WorkspaceEventMail', $super->id], ['job_dismissed', $second, 'BroadcastNotificationCreated', $super->id]],
            DB::table('moderation_events')->where('target_type', 'failed_job')->orderBy('id')->get()->map(fn ($event) => [$event->action, (int) $event->target_id, $event->reason, (int) $event->actor_id])->all());
    }
}

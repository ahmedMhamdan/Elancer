<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\MessageFile;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Plan 030: files sent with a message reach the two participants only, and go with the message when moderation hides it. */
class MessageFilesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('uploads');
        $this->withoutMiddleware(ThrottleRequests::class);
    }

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

    private function pdf(string $name = 'plan.pdf', int $kilobytes = 120): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kilobytes, 'application/pdf');
    }

    public function test_participants_send_files_with_or_without_text_and_only_they_download_them(): void
    {
        [$client, $freelancer, $conversation] = $this->conversation();
        $thread = '/messages/'.$conversation->id;
        $send = fn (array $data) => $this->post($thread, ['client_token' => (string) Str::uuid(), ...$data]);

        $this->actingAs($freelancer);
        $send(['body' => ''])->assertSessionHasErrors('body');
        $send(['files' => [$this->pdf('a.pdf'), $this->pdf('b.pdf'), $this->pdf('c.pdf'), $this->pdf('d.pdf')]])->assertSessionHasErrors('files');
        $send(['files' => [UploadedFile::fake()->create('run.exe', 10, 'text/plain')]])->assertSessionHasErrors('files.0');
        $send(['files' => [UploadedFile::fake()->create('photo.png', 10, 'application/zip')]])->assertSessionHasErrors('files.0');
        $this->assertSame(1, ConversationMessage::query()->count());
        $this->assertSame([], Storage::disk('uploads')->allFiles());

        // Files alone, sent twice by a retry: one message, one copy of each file.
        $token = (string) Str::uuid();
        $payload = ['body' => '', 'client_token' => $token, 'files' => [$this->pdf(), UploadedFile::fake()->create('code.zip', 30, 'application/zip')]];
        $this->post($thread, $payload)->assertRedirect();
        $this->post($thread, ['body' => '', 'client_token' => $token, 'files' => [$this->pdf(), UploadedFile::fake()->create('code.zip', 30, 'application/zip')]])->assertRedirect();
        $message = ConversationMessage::query()->latest('id')->firstOrFail();
        $this->assertSame(['', 2], [$message->body, ConversationMessage::query()->count()]);
        $files = MessageFile::query()->orderBy('id')->get();
        $this->assertSame(['plan.pdf', 'code.zip'], $files->pluck('name')->all());
        $this->assertCount(2, Storage::disk('uploads')->allFiles());
        foreach ($files as $file) {
            $this->assertStringStartsWith('message-files/'.$conversation->id.'/', $file->path);
        }

        $this->actingAs($client)->get($thread)->assertInertia(fn (Assert $page) => $page
            ->has('messages.data.0.files', 2)->where('messages.data.0.files.0.name', 'plan.pdf')->missing('messages.data.0.files.0.path')
            ->where('messages.data.0.body', '')->where('conversation.preview_files', 2)->where('fileLimit', 3));
        $download = $this->get('/messages/files/'.$files[0]->id)->assertOk();
        $this->assertSame('application/octet-stream', $download->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', (string) $download->headers->get('Content-Disposition'));
        $this->actingAs($freelancer)->get('/messages/files/'.$files[1]->id)->assertOk();
        $this->actingAs(User::factory()->create(['onboarding_completed_at' => now(), 'is_admin' => true]))->get('/messages/files/'.$files[0]->id)->assertNotFound();
        $this->actingAs($client)->withSession(['demo' => true])->get('/messages/files/'.$files[0]->id)->assertForbidden();

        // A correction changes the text only.
        $this->actingAs($freelancer)->withSession(['demo' => false])->patch('/messages/items/'.$message->id, ['body' => 'The plan and the code.', 'version' => 1])->assertRedirect();
        $this->assertSame(2, $message->files()->count());
        $this->get($thread)->assertInertia(fn (Assert $page) => $page->where('messages.data.0.body', 'The plan and the code.')->has('messages.data.0.files', 2)->where('conversation.preview_files', 0));
    }

    public function test_a_read_only_thread_or_a_full_budget_takes_no_file(): void
    {
        [$client, $freelancer, $conversation] = $this->conversation();
        config(['filesystems.budgets.account' => 1]);
        $this->actingAs($freelancer)->post('/messages/'.$conversation->id, ['body' => 'Two files.', 'client_token' => (string) Str::uuid(),
            'files' => [$this->pdf('a.pdf', 600), $this->pdf('b.pdf', 600)]])->assertSessionHasErrors('files');
        $this->assertSame([1, 0], [ConversationMessage::query()->count(), MessageFile::query()->count()]);

        // Q57: a declined applicant's thread is read-only, and nothing is left in storage by the refusal.
        config(['filesystems.budgets.account' => 25]);
        Proposal::query()->update(['status' => 'declined']);
        $this->post('/messages/'.$conversation->id, ['body' => 'Late file.', 'client_token' => (string) Str::uuid(), 'files' => [$this->pdf()]])->assertConflict();
        $this->assertSame([], Storage::disk('uploads')->allFiles());
    }

    public function test_hidden_message_files_reach_no_participant_and_the_reviewer_downloads_them_on_record(): void
    {
        [$client, $freelancer, $conversation] = $this->conversation();
        $this->actingAs($freelancer)->post('/messages/'.$conversation->id, ['body' => 'Open this.', 'client_token' => (string) Str::uuid(), 'files' => [$this->pdf('invoice.pdf')]])->assertRedirect();
        $message = ConversationMessage::query()->latest('id')->firstOrFail();
        $file = MessageFile::query()->firstOrFail();
        $this->actingAs($client)->post('/reports', ['target_type' => 'message', 'target_id' => $message->id, 'reason' => 'inappropriate', 'explanation' => 'This breaks the Elancer rules.'])->assertSessionHasNoErrors();
        $report = Report::query()->firstOrFail();
        $admin = User::factory()->withTwoFactor()->create(['is_admin' => true, 'onboarding_completed_at' => now()]);
        $this->actingAs($admin)->withSession(['admin.two_factor_proof' => $admin->id.':'.hash('sha256', (string) $admin->two_factor_secret)]);
        $url = '/admin/reports/'.$report->id;

        // Only while the administrator holds the report in review.
        $this->get($url.'/files/'.$file->id)->assertForbidden();
        $this->patch($url, ['action' => 'start'])->assertRedirect($url);
        $this->patch($url, ['action' => 'hide', 'reason' => 'A harmful file.'])->assertRedirect($url);
        $this->get($url.'/conversation')->assertInertia(fn (Assert $page) => $page->where('messages.data.1.hidden', true)->where('messages.data.1.files.0.name', 'invoice.pdf'));
        $this->get($url.'/files/'.$file->id)->assertOk();
        $this->assertSame(1, DB::table('moderation_events')->where('action', 'conversation_file_read')->where('conversation_id', $conversation->id)->where('actor_id', $admin->id)->count());

        foreach ([$client, $freelancer] as $participant) {
            $this->actingAs($participant)->get('/messages/'.$conversation->id)->assertInertia(fn (Assert $page) => $page->where('messages.data.0.hidden', true)->has('messages.data.0.files', 0));
            $this->get('/messages/files/'.$file->id)->assertNotFound();
        }
    }
}

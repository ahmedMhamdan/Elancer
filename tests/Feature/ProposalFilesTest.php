<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\ProposalFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Plan 030: a proposal's files follow its text, and the client receives exactly a submitted version's. */
class ProposalFilesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('uploads');
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function member(string $email = ''): User
    {
        return User::factory()->create(['onboarding_completed_at' => now(), ...($email !== '' ? ['email' => $email] : [])]);
    }

    private function freelancer(string $email = ''): User
    {
        $user = $this->member($email);
        $user->forceFill(['workspace_role' => WorkspaceRole::Freelancer])->save();
        $profile = $user->profile()->create(['headline' => 'Laravel developer', 'bio' => 'I build accessible applications.']);
        $profile->syncSkillTags(['Laravel']);
        $profile->forceFill(['published_at' => now()])->save();

        return $user;
    }

    private function project(User $owner): Project
    {
        $project = new Project;
        $project->forceFill(['user_id' => $owner->id, 'category_id' => Category::query()->firstOrCreate(['categoryname' => 'Development'])->id,
            'title' => 'Build a website', 'description' => str_repeat('A clear specification for the project. ', 3), 'budget_min' => 100, 'budget_max' => 500,
            'status' => 'published', 'published_at' => now()->subHour(), 'application_closes_at' => now()->addDays(7)])->save();

        return $project;
    }

    /** @return array<string, mixed> */
    private function text(string $action, int $version): array
    {
        return ['action' => $action, 'version' => $version, 'price' => '650.50', 'duration_days' => 14,
            'message' => 'I will build and test your accessible Laravel application.', 'answers' => [], 'samples' => []];
    }

    private function pdf(string $name = 'portfolio.pdf', int $kilobytes = 120): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kilobytes, 'application/pdf');
    }

    public function test_a_client_receives_exactly_the_files_of_the_versions_submitted(): void
    {
        $client = $this->member();
        $freelancer = $this->freelancer();
        $project = $this->project($client);
        $files = '/jobs/'.$project->id.'/proposal/files';

        // A file belongs to a saved draft.
        $this->actingAs($freelancer)->postJson($files, ['file' => $this->pdf()])->assertConflict();
        $this->putJson('/jobs/'.$project->id.'/proposal', $this->text('save', 0))->assertOk();
        $proposal = Proposal::query()->firstOrFail();

        $this->postJson($files, ['file' => $this->pdf()])->assertCreated()->assertJsonPath('files.0.name', 'portfolio.pdf')->assertJsonMissingPath('files.0.path');
        $first = ProposalFile::query()->firstOrFail();
        Storage::disk('uploads')->assertExists($first->path);
        $this->assertStringStartsWith('proposal-files/'.$proposal->id.'/', $first->path);
        // Q71: a file is not a text save, so the draft's version stays where it was.
        $this->assertSame(1, $proposal->fresh()->version);

        // A program, an archive named as a PDF, a file over 5 MB and a forged path are refused; a ZIP is taken.
        $this->postJson($files, ['file' => UploadedFile::fake()->create('run.exe', 10, 'text/plain')])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson($files, ['file' => UploadedFile::fake()->create('archive.pdf', 10, 'application/zip')])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson($files, ['file' => $this->pdf('large.pdf', 5121)])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson($files, ['path' => 'profile-photos/1/x.jpg'])->assertUnprocessable();
        $this->postJson($files, ['file' => UploadedFile::fake()->create('code.zip', 40, 'application/zip')])->assertCreated();
        $this->postJson($files, ['file' => UploadedFile::fake()->create('notes.txt', 2, 'text/plain')])->assertCreated();
        $this->postJson($files, ['file' => $this->pdf('fourth.pdf')])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertSame(3, ProposalFile::query()->count());

        // A file nobody received goes for good.
        $notes = ProposalFile::query()->where('name', 'notes.txt')->firstOrFail();
        $this->deleteJson($files.'/'.$notes->id)->assertOk()->assertJsonCount(2, 'files');
        $this->assertModelMissing($notes);
        Storage::disk('uploads')->assertMissing($notes->path);

        // A draft and its files stay with the freelancer.
        $this->actingAs($client)->get('/proposals/'.$proposal->id)->assertNotFound();
        $this->get('/proposals/'.$proposal->id.'/files/'.$first->id)->assertNotFound();

        $this->actingAs($freelancer)->putJson('/jobs/'.$project->id.'/proposal', $this->text('submit', 1))->assertOk();
        $zip = ProposalFile::query()->where('name', 'code.zip')->firstOrFail();
        $this->assertNotNull($first->fresh()->submitted_at);
        $this->actingAs($client)->get('/proposals/'.$proposal->id)->assertInertia(fn (Assert $page) => $page
            ->has('proposal.content.files', 2)->where('proposal.content.files.0.name', 'portfolio.pdf')->missing('proposal.content.files.0.path')
            ->missing('proposal.files')->missing('proposal.files_changed')->has('proposal.events.0.content.files', 2));
        $download = $this->get('/proposals/'.$proposal->id.'/files/'.$first->id)->assertOk();
        $this->assertSame('application/octet-stream', $download->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', (string) $download->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', (string) $download->headers->get('Cache-Control'));

        // After submitting, a new file is private until the next submission, and a received file stays in the history.
        $this->actingAs($freelancer)->postJson($files, ['file' => $this->pdf('update.pdf')])->assertCreated();
        $update = ProposalFile::query()->where('name', 'update.pdf')->firstOrFail();
        $this->deleteJson($files.'/'.$first->id)->assertOk()->assertJsonCount(2, 'files');
        $this->assertNotNull($first->fresh()->removed_at);
        Storage::disk('uploads')->assertExists($first->path);
        $this->deleteJson($files.'/'.$first->id)->assertNotFound();
        $this->get('/proposals/'.$proposal->id)->assertInertia(fn (Assert $page) => $page->where('proposal.files_changed', true)->has('proposal.files', 2)->has('proposal.content.files', 2));
        $this->actingAs($client)->get('/proposals/'.$proposal->id.'/files/'.$update->id)->assertNotFound();
        $this->get('/proposals/'.$proposal->id)->assertInertia(fn (Assert $page) => $page->has('proposal.content.files', 2));

        $this->actingAs($freelancer)->putJson('/jobs/'.$project->id.'/proposal', $this->text('submit', $proposal->fresh()->version))->assertOk();
        $this->get('/proposals/'.$proposal->id)->assertInertia(fn (Assert $page) => $page->where('proposal.files_changed', false));
        $this->actingAs($client)->get('/proposals/'.$proposal->id)->assertInertia(fn (Assert $page) => $page
            ->where('proposal.content.files', [['id' => $zip->id, 'name' => 'code.zip', 'size' => $zip->size], ['id' => $update->id, 'name' => 'update.pdf', 'size' => $update->size]])
            ->has('proposal.events', 2)->where('proposal.events.1.content.files.0.name', 'portfolio.pdf'));
        $this->get('/proposals/'.$proposal->id.'/files/'.$update->id)->assertOk();
        $this->get('/proposals/'.$proposal->id.'/files/'.$first->id)->assertOk();

        // Nobody else, an administrator included, and a file asked for through another proposal's address.
        $this->actingAs(User::factory()->create(['onboarding_completed_at' => now(), 'is_admin' => true]))->get('/proposals/'.$proposal->id.'/files/'.$first->id)->assertNotFound();
        $other = Proposal::query()->forceCreate(['project_id' => $this->project($client)->id, 'user_id' => $freelancer->id, 'status' => 'draft', 'version' => 1]);
        $this->actingAs($freelancer)->get('/proposals/'.$other->id.'/files/'.$first->id)->assertNotFound();
        $this->deleteJson('/jobs/'.$other->project_id.'/proposal/files/'.$zip->id)->assertNotFound();
    }

    public function test_no_file_goes_where_nobody_receives_it_or_where_editing_is_over(): void
    {
        // Plan 027: a member trying a sample project, which no real client reads.
        $member = $this->freelancer();
        $demo = $this->project($this->member('client-01@'.config('demo.domain')));
        $this->actingAs($member)->putJson('/jobs/'.$demo->id.'/proposal', $this->text('save', 0))->assertOk();
        $this->postJson('/jobs/'.$demo->id.'/proposal/files', ['file' => $this->pdf()])->assertForbidden()
            ->assertJsonPath('message', 'Nobody receives a proposal on a sample project, so it takes no files.');
        $this->get('/jobs/'.$demo->id.'/apply')->assertInertia(fn (Assert $page) => $page->where('trial', true)->has('proposal.files', 0));

        // A declined proposal cannot be changed, files included.
        $client = $this->member();
        $project = $this->project($client);
        $this->putJson('/jobs/'.$project->id.'/proposal', $this->text('save', 0))->assertOk();
        $this->postJson('/jobs/'.$project->id.'/proposal/files', ['file' => $this->pdf()])->assertCreated();
        $this->putJson('/jobs/'.$project->id.'/proposal', $this->text('submit', 1))->assertOk();
        $proposal = Proposal::query()->where('project_id', $project->id)->firstOrFail();
        $file = ProposalFile::query()->where('proposal_id', $proposal->id)->firstOrFail();
        $proposal->forceFill(['status' => 'declined'])->save();
        $this->postJson('/jobs/'.$project->id.'/proposal/files', ['file' => $this->pdf()])->assertConflict();
        $this->deleteJson('/jobs/'.$project->id.'/proposal/files/'.$file->id)->assertConflict();
        $this->assertNull($file->fresh()->removed_at);

        // Plans 025 and 027: no file leaves storage through a demo session, even the account's own.
        $this->withSession(['demo' => true])->get('/proposals/'.$proposal->id.'/files/'.$file->id)->assertForbidden();
    }

    public function test_the_upload_budget_refuses_new_files_and_keeps_the_old_ones(): void
    {
        config(['filesystems.budgets.account' => 1, 'filesystems.budgets.site' => 750]);
        $client = $this->member();
        $freelancer = $this->freelancer();
        $project = $this->project($client);
        $files = '/jobs/'.$project->id.'/proposal/files';
        $this->actingAs($freelancer)->putJson('/jobs/'.$project->id.'/proposal', $this->text('save', 0))->assertOk();
        $this->postJson($files, ['file' => $this->pdf('first.pdf', 700)])->assertCreated();
        $this->postJson($files, ['file' => $this->pdf('second.pdf', 400)])->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Your account has used its 1 MB for files. Files you already attached stay; new ones cannot be added.');
        $this->assertSame(['first.pdf'], ProposalFile::query()->pluck('name')->all());
        $this->assertCount(1, Storage::disk('uploads')->allFiles());

        // The same room counts a brief's files, and the whole site has its own limit.
        $this->actingAs($client)->post('/my-projects')->assertRedirect();
        $draft = Project::query()->where('status', 'draft')->firstOrFail();
        $this->postJson('/my-projects/'.$draft->id.'/attachments', ['file' => $this->pdf('brief.pdf', 400)])->assertCreated();
        config(['filesystems.budgets.account' => 25, 'filesystems.budgets.site' => 1]);
        $this->postJson('/my-projects/'.$draft->id.'/attachments', ['file' => $this->pdf('more.pdf', 10)])->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Elancer has no room for new files right now. Files you already attached stay.');
    }

    public function test_deleting_an_account_removes_its_proposal_and_conversation_files(): void
    {
        $client = $this->member();
        $freelancer = $this->freelancer();
        $project = $this->project($client);
        $this->actingAs($freelancer)->putJson('/jobs/'.$project->id.'/proposal', $this->text('save', 0))->assertOk();
        $this->postJson('/jobs/'.$project->id.'/proposal/files', ['file' => $this->pdf()])->assertCreated();
        $this->putJson('/jobs/'.$project->id.'/proposal', $this->text('submit', 1))->assertOk();
        $proposal = Proposal::query()->firstOrFail();
        $this->actingAs($client)->post('/proposals/'.$proposal->id.'/conversation', ['body' => 'Let us talk.', 'client_token' => (string) Str::uuid()])->assertRedirect();
        $conversation = Conversation::query()->firstOrFail();
        $this->actingAs($freelancer)->post('/messages/'.$conversation->id, ['body' => 'My plan.', 'client_token' => (string) Str::uuid(), 'files' => [$this->pdf('plan.pdf')]])->assertRedirect();
        $this->assertSame(1, ConversationMessage::query()->whereHas('files')->count());
        $this->assertCount(2, Storage::disk('uploads')->allFiles());

        $this->delete('/settings/profile', ['password' => 'password'])->assertSessionHasNoErrors()->assertRedirect('/');
        $this->assertModelMissing($proposal);
        $this->assertSame([], Storage::disk('uploads')->allFiles());
    }
}

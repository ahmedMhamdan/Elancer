<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('uploads');
    }

    private function member(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['onboarding_completed_at' => now()], $attributes));
    }

    private function draft(User $owner): Project
    {
        $this->actingAs($owner)->post('/my-projects')->assertRedirect();

        return Project::query()->latest('id')->firstOrFail();
    }

    private function pdf(string $name = 'brief.pdf', int $kilobytes = 120): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kilobytes, 'application/pdf');
    }

    /** A published brief carrying a PDF and an image, built through the owner's own actions. */
    private function published(User $owner): Project
    {
        $draft = $this->draft($owner);
        $this->postJson('/my-projects/'.$draft->id.'/attachments', ['file' => $this->pdf()])->assertCreated();
        $this->postJson('/my-projects/'.$draft->id.'/attachments', ['file' => UploadedFile::fake()->image('wireframe.png')])->assertCreated();
        $this->postJson('/my-projects/'.$draft->id.'/publish', [
            'version' => $draft->fresh()->version, 'title' => 'Build a bilingual website', 'description' => str_repeat('Clear description of the expected deliverables. ', 3),
            'category_id' => Category::create(['categoryname' => 'Web development'])->id, 'skills' => Skill::query()->limit(2)->pluck('id')->all(),
            'budget_min' => 200, 'budget_max' => 500, 'application_closes_at' => now()->addDays(7)->toIso8601String(),
        ])->assertOk();

        return $draft->fresh();
    }

    public function test_the_owner_manages_files_on_a_draft_only_and_within_the_limits(): void
    {
        $owner = $this->member();
        $draft = $this->draft($owner);
        $address = '/my-projects/'.$draft->id.'/attachments';

        $this->postJson($address, ['file' => $this->pdf()])->assertCreated()->assertJsonPath('attachments.0.name', 'brief.pdf')->assertJsonMissingPath('attachments.0.path');
        $first = ProjectAttachment::query()->firstOrFail();
        Storage::disk('uploads')->assertExists($first->path);
        $this->assertStringStartsWith('project-attachments/'.$draft->id.'/', $first->path);
        // Q71: a file is not a text save, so the draft's version stays where it was.
        $this->assertSame(1, $draft->fresh()->version);

        // An archive, a text file named like a program, content that is not what its name says, a file over 5 MB and a request without a file.
        $this->postJson($address, ['file' => UploadedFile::fake()->create('tools.zip', 10, 'application/zip')])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson($address, ['file' => UploadedFile::fake()->create('run.exe', 10, 'text/plain')])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson($address, ['file' => UploadedFile::fake()->create('archive.pdf', 10, 'application/zip')])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson($address, ['file' => UploadedFile::fake()->create('photo.pdf', 10, 'image/jpeg')])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson($address, ['file' => UploadedFile::fake()->create('page.txt', 10, 'application/octet-stream')])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson($address, ['file' => $this->pdf('large.pdf', 5121)])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson($address, ['name' => 'forged.pdf', 'path' => 'profile-photos/1/x.jpg'])->assertUnprocessable();
        $this->assertSame(1, ProjectAttachment::query()->count());
        $this->assertCount(1, Storage::disk('uploads')->allFiles());

        $this->postJson($address, ['file' => UploadedFile::fake()->image('wireframe.png')])->assertCreated();
        // A plain note with commas in it is detected as text/csv; it is still a text file named .txt.
        $this->postJson($address, ['file' => UploadedFile::fake()->create('NOTES.TXT', 4, 'text/csv')])->assertCreated()->assertJsonCount(3, 'attachments');
        $this->postJson($address, ['file' => $this->pdf('fourth.pdf')])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertCount(3, Storage::disk('uploads')->allFiles());

        $this->get('/my-projects/'.$draft->id.'/edit')->assertInertia(fn (Assert $page) => $page->has('attachments', 3)->where('attachmentLimit', 3)
            ->where('attachments.0.name', 'brief.pdf')->where('attachments.0.size', $first->size)->missing('attachments.0.path')->missing('project.attachments'));
        $this->get('/jobs/'.$draft->id.'/attachments/'.$first->id)->assertOk()->assertDownload('brief.pdf');

        // Another member reaches nothing of a draft, and a file is not reachable through another project's address.
        $other = $this->member();
        $theirs = $this->draft($other);
        $this->postJson($address, ['file' => $this->pdf()])->assertNotFound();
        $this->deleteJson($address.'/'.$first->id)->assertNotFound();
        $this->get('/jobs/'.$draft->id.'/attachments/'.$first->id)->assertNotFound();
        $this->deleteJson('/my-projects/'.$theirs->id.'/attachments/'.$first->id)->assertNotFound();
        $this->get('/jobs/'.$theirs->id.'/attachments/'.$first->id)->assertNotFound();
        $this->assertSame(3, ProjectAttachment::query()->count());

        $this->actingAs($owner)->deleteJson($address.'/'.$first->id)->assertOk()->assertJsonCount(2, 'attachments');
        Storage::disk('uploads')->assertMissing($first->path);
        $this->assertNull($first->fresh());
    }

    public function test_files_are_frozen_with_the_published_brief(): void
    {
        $owner = $this->member();
        $project = $this->published($owner);
        $file = ProjectAttachment::query()->firstOrFail();

        $this->postJson('/my-projects/'.$project->id.'/attachments', ['file' => $this->pdf('late.pdf')])->assertStatus(409);
        $this->deleteJson('/my-projects/'.$project->id.'/attachments/'.$file->id)->assertStatus(409);
        $this->assertSame(2, ProjectAttachment::query()->count());
        Storage::disk('uploads')->assertExists($file->path);
        $this->assertCount(2, Storage::disk('uploads')->allFiles());
    }

    public function test_everyone_sees_the_count_and_only_active_verified_members_get_the_files(): void
    {
        $owner = $this->member();
        $project = $this->published($owner);
        $file = ProjectAttachment::query()->orderBy('id')->firstOrFail();
        $page = '/jobs/'.$project->id;
        $download = $page.'/attachments/'.$file->id;
        $hidden = fn (Assert $inertia) => $inertia->where('attachments.count', 2)->where('attachments.files', null);
        $listed = fn (Assert $inertia) => $inertia->where('attachments.count', 2)->has('attachments.files', 2)
            ->where('attachments.files.0.name', 'brief.pdf')->where('attachments.files.1.name', 'wireframe.png')->missing('attachments.files.0.path');

        // A guest: the number, no name anywhere on the page, and the address asks for a sign-in.
        auth()->logout();
        $this->get($page)->assertOk()->assertInertia($hidden)->assertDontSee('brief.pdf')->assertDontSee('wireframe.png');
        $this->get('/jobs')->assertInertia(fn (Assert $inertia) => $inertia->where('projects.data.0.attachments', 2))->assertDontSee('brief.pdf');
        $this->get($download)->assertRedirect('/login');

        // Signed in is not enough: the email must be verified and the account active.
        $this->actingAs(User::factory()->unverified()->create())->get($page)->assertInertia($hidden);
        $this->get($download)->assertRedirect(route('verification.notice'));
        $this->actingAs($this->member(['status' => 'suspended']))->get($page)->assertInertia($hidden);
        $this->get($download)->assertForbidden();

        // Q11 asks for nothing more, so a verified member who has not finished onboarding downloads too.
        $this->actingAs(User::factory()->create())->get($page)->assertInertia($listed);
        $response = $this->get($download)->assertOk()->assertDownload('brief.pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame('application/octet-stream', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->actingAs($owner)->get($page)->assertInertia($listed);
        $this->get($download)->assertOk();

        // Hiring closed: the brief stays public, and so do its files.
        $project->forceFill(['status' => 'hired'])->save();
        $this->actingAs($this->member())->get($page)->assertInertia($listed);
        $this->get($download)->assertOk();
    }

    public function test_a_hidden_project_keeps_its_files_from_everyone_but_its_owner(): void
    {
        $owner = $this->member();
        $project = $this->published($owner);
        $file = ProjectAttachment::query()->firstOrFail();
        $download = '/jobs/'.$project->id.'/attachments/'.$file->id;
        $member = $this->member();

        $project->forceFill(['moderated_at' => now()])->save();
        $this->actingAs($member)->get('/jobs/'.$project->id)->assertNotFound();
        $this->get($download)->assertNotFound();
        $this->actingAs($owner)->get('/jobs/'.$project->id)->assertInertia(fn (Assert $page) => $page->has('attachments.files', 2));
        $this->get($download)->assertOk();

        // Q64: a suspended client still reads their own brief and its files; the project left public pages.
        $project->forceFill(['moderated_at' => null])->save();
        $owner->forceFill(['status' => 'suspended'])->save();
        $this->actingAs($owner->fresh())->get('/jobs/'.$project->id)->assertInertia(fn (Assert $page) => $page->has('attachments.files', 2));
        $this->get($download)->assertOk();
        $this->actingAs($member)->get($download)->assertNotFound();

        // A file whose stored copy is gone answers as missing, not as an error.
        $owner->forceFill(['status' => 'active'])->save();
        Storage::disk('uploads')->delete($file->path);
        $this->get($download)->assertNotFound();
    }

    public function test_permanent_deletion_and_account_deletion_remove_the_files(): void
    {
        $owner = $this->member();
        $draft = $this->draft($owner);
        $this->postJson('/my-projects/'.$draft->id.'/attachments', ['file' => $this->pdf()])->assertCreated();
        $file = ProjectAttachment::query()->firstOrFail();

        // Q72: Trash keeps the files, because the draft can come back.
        $this->delete('/my-projects/'.$draft->id)->assertRedirect();
        Storage::disk('uploads')->assertExists($file->path);
        $this->postJson('/my-projects/'.$draft->id.'/attachments', ['file' => $this->pdf('second.pdf')])->assertNotFound();
        $this->get('/jobs/'.$draft->id.'/attachments/'.$file->id)->assertNotFound();
        $this->post('/my-projects/'.$draft->id.'/restore')->assertRedirect();
        $this->get('/jobs/'.$draft->id.'/attachments/'.$file->id)->assertOk();

        $this->delete('/my-projects/'.$draft->id)->assertRedirect();
        $this->delete('/my-projects/'.$draft->id.'/permanent')->assertRedirect();
        $this->assertSame(0, ProjectAttachment::query()->count());
        $this->assertSame([], Storage::disk('uploads')->allFiles());

        // Deleting the account removes the files of every brief it owned, trashed ones included.
        $kept = $this->draft($owner);
        $trashed = $this->draft($owner);
        $this->postJson('/my-projects/'.$kept->id.'/attachments', ['file' => $this->pdf('kept.pdf')])->assertCreated();
        $this->postJson('/my-projects/'.$trashed->id.'/attachments', ['file' => $this->pdf('trashed.pdf')])->assertCreated();
        $this->delete('/my-projects/'.$trashed->id)->assertRedirect();
        $bystander = $this->draft($other = $this->member());
        $this->postJson('/my-projects/'.$bystander->id.'/attachments', ['file' => $this->pdf('bystander.pdf')])->assertCreated();
        $this->assertCount(3, Storage::disk('uploads')->allFiles());

        $this->actingAs($owner)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');
        $this->assertNull($owner->fresh());
        $remaining = Storage::disk('uploads')->allFiles();
        $this->assertCount(1, $remaining);
        $this->assertStringStartsWith('project-attachments/'.$bystander->id.'/', $remaining[0]);
        $this->assertSame(1, ProjectAttachment::query()->where('project_id', $bystander->id)->count());
        $this->assertNotNull($other->fresh());
    }
}

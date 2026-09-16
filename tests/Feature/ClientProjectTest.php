<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Project;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientProjectTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['onboarding_completed_at' => now()]);
    }

    private function draft(User $owner): Project
    {
        $this->actingAs($owner)->post('/my-projects')->assertRedirect();

        return Project::query()->latest('id')->firstOrFail();
    }

    private function complete(Project $project): array
    {
        return ['version' => $project->version, 'title' => 'Build a bilingual website', 'description' => str_repeat('Clear description of the expected deliverables. ', 3), 'category_id' => Category::create(['categoryname' => 'Web development'])->id, 'skills' => Skill::query()->limit(2)->pluck('id')->all(), 'budget_min' => 200, 'budget_max' => 500, 'application_closes_at' => now()->addDays(7)->toIso8601String(), 'screening_questions' => ['What is your approach?']];
    }

    public function test_owner_can_save_partial_draft_and_publish_a_valid_project(): void
    {
        $draft = $this->draft($this->owner());
        $this->patchJson('/my-projects/'.$draft->id, ['version' => 1, 'title' => 'Partial'])->assertOk()->assertJsonPath('project.version', 2);
        $this->assertSame('draft', $draft->fresh()->status);
        $this->get('/jobs/'.$draft->id)->assertNotFound();
        $this->postJson('/my-projects/'.$draft->id.'/publish', $this->complete($draft->fresh()))->assertOk()->assertJsonPath('project.status', 'published');
        $this->get('/jobs/'.$draft->id)->assertOk();
        $this->assertNotNull($draft->fresh()->published_at);
    }

    public function test_other_users_guests_and_suspended_users_cannot_manage_drafts(): void
    {
        $draft = $this->draft($this->owner());
        $this->actingAs($this->owner())->get('/my-projects/'.$draft->id.'/edit')->assertNotFound();
        $this->patchJson('/my-projects/'.$draft->id, ['version' => 1, 'title' => 'Stolen'])->assertNotFound();
        $this->delete('/my-projects/'.$draft->id)->assertNotFound();
        $this->actingAs(User::factory()->create(['status' => 'suspended', 'onboarding_completed_at' => now()]))->post('/my-projects')->assertForbidden();
        $this->assertNull($draft->fresh()->title);
    }

    public function test_stale_save_and_publish_return_current_version_without_overwriting(): void
    {
        $draft = $this->draft($this->owner());
        $this->patchJson('/my-projects/'.$draft->id, ['version' => 1, 'title' => 'First tab'])->assertOk();
        $this->patchJson('/my-projects/'.$draft->id, ['version' => 1, 'title' => 'Second tab'])->assertStatus(409)->assertJsonPath('project.title', 'First tab');
        $this->postJson('/my-projects/'.$draft->id.'/publish', $this->complete($draft))->assertStatus(409);
        $this->assertSame('First tab', $draft->fresh()->title);
    }

    public function test_publication_validates_fields_and_cannot_be_forged_by_draft_save(): void
    {
        $draft = $this->draft($this->owner());
        $this->postJson('/my-projects/'.$draft->id.'/publish', ['version' => 1])->assertUnprocessable()->assertJsonValidationErrors(['title', 'description', 'category_id', 'skills', 'budget_min', 'budget_max', 'application_closes_at']);
        $this->patchJson('/my-projects/'.$draft->id, ['version' => 1, 'status' => 'published', 'user_id' => 42])->assertUnprocessable()->assertJsonValidationErrors(['status', 'user_id']);
        $data = $this->complete($draft);
        $data['application_closes_at'] = now()->subDay()->toIso8601String();
        $data['budget_max'] = 100;
        $this->postJson('/my-projects/'.$draft->id.'/publish', $data)->assertUnprocessable()->assertJsonValidationErrors(['application_closes_at', 'budget_max']);
        $this->assertSame('draft', $draft->fresh()->status);
    }

    public function test_trash_restore_and_permanent_deletion_are_draft_only(): void
    {
        $draft = $this->draft($this->owner());
        $this->delete('/my-projects/'.$draft->id.'/permanent')->assertStatus(409);
        $this->delete('/my-projects/'.$draft->id)->assertRedirect();
        $this->post('/my-projects/'.$draft->id.'/restore')->assertRedirect();
        $this->assertFalse($draft->fresh()->trashed());
        $this->delete('/my-projects/'.$draft->id)->assertRedirect();
        $this->delete('/my-projects/'.$draft->id.'/permanent')->assertRedirect();
        $this->assertDatabaseMissing('projects', ['id' => $draft->id]);
        $published = $this->draft($this->owner());
        $this->postJson('/my-projects/'.$published->id.'/publish', $this->complete($published))->assertOk();
        $this->delete('/my-projects/'.$published->id)->assertStatus(409);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Models\Category;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkspaceSwitchTest extends TestCase
{
    use RefreshDatabase;

    private function member(WorkspaceRole $workspace): User
    {
        $user = User::factory()->create(['workspace_role' => $workspace, 'onboarding_completed_at' => now()]);
        $profile = $user->profile()->create(['headline' => 'Laravel developer', 'bio' => 'I build accessible applications.']);
        $profile->syncSkillTags(['Laravel']);
        $profile->forceFill(['company' => 'Northwind', 'published_at' => now()])->save();

        return $user;
    }

    private function project(User $owner): Project
    {
        $project = new Project;
        $project->forceFill([
            'user_id' => $owner->id, 'category_id' => Category::create(['categoryname' => 'Development'])->id,
            'title' => 'Build a website', 'description' => str_repeat('A clear specification for the project. ', 3),
            'budget_min' => 100, 'budget_max' => 500, 'status' => 'published',
            'published_at' => now()->subHour(), 'application_closes_at' => now()->addDays(7),
        ])->save();

        return $project;
    }

    public function test_switching_changes_what_is_shown_and_nothing_else(): void
    {
        $user = $this->member(WorkspaceRole::Freelancer);
        $before = $user->profile->fresh()->getAttributes();

        $this->actingAs($user)->from('/dashboard')->patch('/workspace', ['workspace' => 'client'])->assertSessionHasNoErrors()->assertRedirect('/dashboard');
        $this->assertSame(WorkspaceRole::Client, $user->fresh()->workspace_role);
        // The freelancer profile is kept as it was, still published, for the way back.
        $this->assertSame($before, $user->profile->fresh()->getAttributes());
        $this->assertSame(1, $user->profile->skillTags()->count());
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('overview.client', true)->where('auth.user.workspace_role', 'client'));

        $this->from('/my-profile')->patch('/workspace', ['workspace' => 'freelancer'])->assertRedirect('/my-profile');
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('overview.client', false)->where('overview.profile_checks.skills', true));
    }

    public function test_switch_is_refused_for_unknown_workspaces_guests_and_unfinished_onboarding(): void
    {
        $this->patch('/workspace', ['workspace' => 'client'])->assertRedirect('/login');

        $user = $this->member(WorkspaceRole::Freelancer);
        foreach ([[], ['workspace' => 'admin'], ['workspace' => ['client']]] as $payload) {
            $this->actingAs($user)->patch('/workspace', $payload)->assertSessionHasErrors('workspace');
        }
        // Only the workspace is read from the request.
        $this->patch('/workspace', ['workspace' => 'client', 'is_admin' => true, 'status' => 'suspended'])->assertSessionHasNoErrors();
        $this->assertSame(['client', false, 'active'], [$user->fresh()->workspace_role->value, $user->fresh()->is_admin, $user->fresh()->status->value]);

        $unfinished = User::factory()->create(['workspace_role' => null, 'onboarding_completed_at' => null]);
        $this->actingAs($unfinished)->patch('/workspace', ['workspace' => 'client'])->assertRedirect('/onboarding');
        $this->assertNull($unfinished->fresh()->workspace_role);
    }

    public function test_workspace_never_decides_what_a_member_may_do(): void
    {
        $owner = $this->member(WorkspaceRole::Client);
        $project = $this->project($owner);
        $member = $this->member(WorkspaceRole::Client);

        // In the Client workspace a member still applies with their published freelancer profile.
        $this->actingAs($member)->putJson('/jobs/'.$project->id.'/proposal', ['action' => 'submit', 'version' => 0, 'price' => '300.00', 'duration_days' => 7,
            'message' => 'I will build and test your accessible application.', 'answers' => [], 'samples' => []])->assertOk();
        $this->assertSame('submitted', Proposal::query()->where('project_id', $project->id)->where('user_id', $member->id)->value('status'));
        // Being in the Client workspace opens nobody else's project.
        $this->get('/my-projects/'.$project->id.'/proposals')->assertNotFound();

        // In the Freelancer workspace the same member still starts a project and reads its applicants.
        $this->patch('/workspace', ['workspace' => 'freelancer'])->assertSessionHasNoErrors();
        $this->post('/my-projects')->assertRedirect();
        $own = Project::query()->where('user_id', $member->id)->firstOrFail();
        $this->get('/my-projects/'.$own->id.'/proposals')->assertOk();
        $this->get('/my-projects/'.$project->id.'/proposals')->assertNotFound();
        // The owner, now in the Freelancer workspace, still reads the applicants of their own project.
        $this->actingAs($owner)->patch('/workspace', ['workspace' => 'freelancer'])->assertSessionHasNoErrors();
        $this->get('/my-projects/'.$project->id.'/proposals')->assertOk()->assertInertia(fn (Assert $page) => $page->has('proposals.data', 1));
    }
}

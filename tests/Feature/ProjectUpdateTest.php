<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Models\Category;
use App\Models\Project;
use App\Models\ProjectClarification;
use App\Models\Proposal;
use App\Models\Skill;
use App\Models\User;
use App\Notifications\WorkspaceEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function owner(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['workspace_role' => WorkspaceRole::Client, 'onboarding_completed_at' => now()], $attributes));
    }

    private function freelancer(): User
    {
        $user = User::factory()->create(['workspace_role' => WorkspaceRole::Freelancer, 'onboarding_completed_at' => now()]);
        $profile = $user->profile()->create(['headline' => 'Laravel developer', 'bio' => 'I build accessible applications.']);
        $profile->syncSkillTags(['Laravel']);
        $profile->forceFill(['published_at' => now()])->save();

        return $user;
    }

    private function project(User $owner, array $attributes = []): Project
    {
        $project = new Project;
        $project->forceFill(array_merge([
            'user_id' => $owner->id, 'category_id' => Category::create(['categoryname' => 'Development'])->id,
            'title' => 'Build a website', 'description' => str_repeat('A clear specification for the project. ', 3),
            'budget_min' => 100, 'budget_max' => 500, 'status' => 'published',
            'published_at' => now()->subDays(3), 'application_closes_at' => now()->addDays(7),
        ], $attributes))->save();
        $project->skills()->sync(Skill::query()->where('name', 'Laravel')->pluck('id'));

        return $project;
    }

    private function applicant(Project $project, string $status): User
    {
        $user = $this->freelancer();
        (new Proposal)->forceFill(['project_id' => $project->id, 'user_id' => $user->id, 'status' => $status, 'submitted_at' => $status === 'draft' ? null : now()])->save();

        return $user;
    }

    public function test_only_the_owner_moves_the_cutoff_later_and_that_reopens_applications(): void
    {
        $owner = $this->owner();
        $project = $this->project($owner, ['application_closes_at' => now()->subDay()]);
        $freelancer = $this->freelancer();
        $url = '/my-projects/'.$project->id.'/cutoff';
        $page = '/jobs/'.$project->id;
        $later = now()->addDays(5)->startOfMinute();

        // Closed: nobody new can apply, and nobody but the owner can move the cutoff.
        $this->actingAs($freelancer)->getJson($page.'/apply')->assertStatus(409);
        $this->patchJson($url, ['application_closes_at' => $later->toIso8601String()])->assertNotFound();
        $this->actingAs($this->owner())->patchJson($url, ['application_closes_at' => $later->toIso8601String()])->assertNotFound();
        $this->actingAs($owner)->from($page)->patch($url, ['application_closes_at' => now()->subHour()->toIso8601String()])->assertSessionHasErrors('application_closes_at');
        $this->assertTrue($project->fresh()->application_closes_at->isPast());

        $this->from($page)->patch($url, ['application_closes_at' => $later->toIso8601String()])->assertRedirect($page)->assertSessionHasNoErrors();
        $fresh = $project->fresh();
        $this->assertTrue($fresh->application_closes_at->equalTo($later));
        // Only the cutoff moved; the published terms are what applicants already read.
        $this->assertSame([$project->title, $project->description, $project->budget_min, $project->budget_max], [$fresh->title, $fresh->description, $fresh->budget_min, $fresh->budget_max]);
        $this->get($page)->assertInertia(fn (Assert $inertia) => $inertia->where('project.open', true)->where('can.extend', true)->where('can.clarify', true));
        $this->actingAs($freelancer)->get($page)->assertInertia(fn (Assert $inertia) => $inertia->where('project.open', true)->where('can.extend', false)->where('can.clarify', false));
        $this->get($page.'/apply')->assertOk();

        // "Extend" means later than the current cutoff, never the same time or an earlier one.
        $this->actingAs($owner)->from($page)->patch($url, ['application_closes_at' => $later->toIso8601String()])->assertSessionHasErrors('application_closes_at');
        $this->from($page)->patch($url, ['application_closes_at' => now()->addDay()->toIso8601String()])->assertSessionHasErrors('application_closes_at');

        // Q64, Q68 and an accepted offer each stop the change; a draft is edited in its own editor.
        $further = ['application_closes_at' => $later->addDays(3)->toIso8601String()];
        $owner->forceFill(['status' => 'suspended'])->save();
        $this->patchJson($url, $further)->assertForbidden();
        $owner->forceFill(['status' => 'active'])->save();
        foreach ([['moderated_at' => now()], ['moderated_at' => null, 'status' => 'hired'], ['status' => 'draft']] as $state) {
            $project->forceFill($state)->save();
            $this->patchJson($url, $further)->assertStatus(409);
        }
        $this->assertTrue($project->fresh()->application_closes_at->equalTo($later));
    }

    public function test_clarifications_are_appended_under_the_unchanged_brief_and_reach_only_current_applicants(): void
    {
        $owner = $this->owner(['name' => 'Layla Haddad']);
        $project = $this->project($owner);
        $people = [];
        foreach (['submitted', 'reopened', 'withdrawn', 'declined', 'draft', 'blocked'] as $state) {
            $people[$state] = $this->applicant($project, $state === 'blocked' ? 'submitted' : $state);
        }
        DB::table('user_blocks')->insert(['user_id' => $people['blocked']->id, 'blocked_user_id' => $owner->id]);
        $url = '/my-projects/'.$project->id.'/clarifications';
        $first = 'The site needs English and Arabic from the first release.';
        $second = 'Delivery includes the source files and a short handover call.';

        $this->actingAs($people['submitted'])->postJson($url, ['body' => $first])->assertNotFound();
        $this->actingAs($owner)->post($url, ['body' => 'Too short'])->assertSessionHasErrors('body');
        $this->assertSame(0, $project->clarifications()->count());
        $this->post($url, ['body' => $first])->assertRedirect()->assertSessionHasNoErrors();
        $this->post($url, ['body' => $second])->assertSessionHasNoErrors();

        // Public and dated, oldest first, under a brief that did not change.
        $this->actingAs($people['draft'])->get('/jobs/'.$project->id)->assertOk()->assertInertia(fn (Assert $inertia) => $inertia
            ->where('project.description', $project->description)
            ->has('clarifications', 2)
            ->where('clarifications.0.body', $first)->where('clarifications.1.body', $second)
            ->has('clarifications.0.created_at')
            ->where('can.clarify', false)->where('can.extend', false));
        $this->assertSame($project->description, $project->fresh()->description);

        // One notification per note for applicants still in the running; nobody else hears about it.
        $received = fn (User $user) => $user->notifications()->get()->where('data.kind', 'project_clarified');
        $this->assertSame(2, $received($people['submitted'])->count());
        $this->assertSame(2, $received($people['reopened'])->count());
        foreach (['withdrawn', 'declined', 'draft', 'blocked'] as $state) {
            $this->assertSame(0, $received($people[$state])->count(), $state);
        }
        $this->assertSame(0, $owner->notifications()->count());
        // Q18: the client is named as on the public brief, and the link opens that brief.
        $this->assertSame(['kind' => 'project_clarified', 'href' => '/jobs/'.$project->id, 'title' => 'Build a website', 'actor' => 'Layla', 'conversation' => null], $received($people['submitted'])->first()->data);
        $this->assertSame('hiring', (new WorkspaceEvent('project_clarified', '/jobs/'.$project->id))->category());

        // Moderation, an accepted offer, the cap and suspension each stop further notes.
        $project->forceFill(['moderated_at' => now()])->save();
        $this->actingAs($owner)->postJson($url, ['body' => $first])->assertStatus(409);
        $project->forceFill(['moderated_at' => null, 'status' => 'hired'])->save();
        $this->postJson($url, ['body' => $first])->assertStatus(409);
        $project->forceFill(['status' => 'published'])->save();
        for ($i = 2; $i < ProjectClarification::LIMIT; $i++) {
            (new ProjectClarification)->forceFill(['project_id' => $project->id, 'body' => 'Another dated note for the applicants.'])->save();
        }
        $this->postJson($url, ['body' => $first])->assertStatus(409);
        $this->get('/jobs/'.$project->id)->assertInertia(fn (Assert $inertia) => $inertia->where('can.clarify', false)->where('can.extend', true));
        $owner->forceFill(['status' => 'suspended'])->save();
        $this->postJson($url, ['body' => $first])->assertForbidden();
        $this->assertSame(ProjectClarification::LIMIT, $project->clarifications()->count());
        $this->assertSame(2, $received($people['submitted'])->count());
    }
}

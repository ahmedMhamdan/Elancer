<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_search_options_exclude_deleted_categories_and_internal_fields(): void
    {
        $visible = Category::create(['categoryname' => 'Public category']);
        $deleted = Category::create(['categoryname' => 'Removed category']);
        $deleted->delete();

        $response = $this->getJson('/search/filters')->assertOk();
        $response->assertJsonFragment(['id' => $visible->id, 'slug' => $visible->slug, 'categoryname' => $visible->categoryname])
            ->assertJsonMissing(['categoryname' => $deleted->categoryname]);
        foreach ($response->json('categories') as $category) {
            $this->assertEqualsCanonicalizing(['id', 'slug', 'categoryname'], array_keys($category));
        }
        $this->assertNotEmpty($response->json('skills'));
        foreach ($response->json('skills') as $skill) {
            $this->assertEqualsCanonicalizing(['id', 'name'], array_keys($skill));
        }
    }

    private function project(array $overrides = [], array $skills = []): Project
    {
        $category = Category::create(['categoryname' => 'Development']);
        $project = new Project;
        $project->forceFill(array_merge([
            'user_id' => User::factory()->create()->id, 'category_id' => $category->id,
            'title' => 'Build a Laravel website', 'description' => str_repeat('A clear project description. ', 3),
            'budget_min' => 100, 'budget_max' => 500, 'status' => 'published',
            'published_at' => now()->subHour(), 'application_closes_at' => now()->addDays(7),
        ], $overrides))->save();
        $project->skills()->sync($skills);

        return $project;
    }

    public function test_public_results_exclude_drafts_deleted_future_and_ineligible_owners(): void
    {
        $visible = $this->project();
        $this->project(['status' => 'draft']);
        $this->project()->delete();
        $this->project(['published_at' => now()->addHour()]);
        $this->project(['user_id' => User::factory()->unverified()->create()->id]);
        $this->project(['user_id' => User::factory()->create(['status' => 'suspended'])->id]);
        $hidden = $this->project();
        $hidden->category->delete();
        $this->get('/jobs')->assertOk()->assertInertia(fn (Assert $page) => $page->has('projects.data', 1)->where('projects.data.0.id', $visible->id)->missing('projects.data.0.user_id'));
    }

    public function test_combined_filters_match_literal_text_all_skills_budget_overlap_and_date(): void
    {
        $skills = Skill::query()->limit(2)->pluck('id')->all();
        $match = $this->project(['title' => '100%_ready Laravel', 'budget_min' => 200, 'budget_max' => 800], $skills);
        $this->project(['title' => '100XXready Laravel', 'category_id' => $match->category_id], $skills);
        $this->project(['title' => $match->title, 'category_id' => $match->category_id], [$skills[0]]);
        $this->project(['title' => $match->title, 'category_id' => $match->category_id, 'published_at' => now()->subDays(9)], $skills);
        $query = http_build_query(['q' => '100%_READY', 'category' => $match->category->slug, 'skills' => $skills, 'skill_mode' => 'all', 'budget_min' => 600, 'budget_max' => 900, 'posted' => '7']);
        $this->get('/jobs?'.$query)->assertInertia(fn (Assert $page) => $page->has('projects.data', 1)->where('projects.data.0.id', $match->id));
    }

    /** @return list<int> */
    private function found(array $query): array
    {
        return collect($this->get('/jobs?'.http_build_query($query))->assertOk()->viewData('page')['props']['projects']['data'])->pluck('id')->sort()->values()->all();
    }

    public function test_selected_skills_match_any_by_default_and_all_only_on_request(): void
    {
        $skills = Skill::query()->limit(3)->pluck('id')->all();
        $both = $this->project([], [$skills[0], $skills[1]]);
        $one = $this->project([], [$skills[1]]);
        $this->project([], [$skills[2]]);
        $this->project();
        $chosen = [$skills[0], $skills[1]];
        $this->assertSame([$both->id, $one->id], $this->found(['skills' => $chosen]));
        $this->assertSame([$both->id, $one->id], $this->found(['skills' => $chosen, 'skill_mode' => 'any']));
        $this->assertSame([$both->id], $this->found(['skills' => $chosen, 'skill_mode' => 'all']));
        // The switch alone narrows nothing and is kept for the page.
        $this->get('/jobs?skill_mode=all')->assertInertia(fn (Assert $page) => $page->where('filters.skill_mode', 'all')->has('projects.data', 4));
        $this->get('/jobs')->assertInertia(fn (Assert $page) => $page->where('filters.skill_mode', 'any')->where('filters.proposals', 'any'));
    }

    public function test_received_count_presets_use_the_public_count_of_submitted_proposals(): void
    {
        $freelancers = User::factory()->count(22)->create();
        // Q60: a withdrawn proposal stays counted, a draft never is.
        $receive = function (int $submitted, int $withdrawn = 0, int $drafts = 0) use ($freelancers): Project {
            $project = $this->project();
            foreach ($freelancers->take($submitted + $withdrawn + $drafts) as $index => $freelancer) {
                $status = $index < $submitted ? 'submitted' : ($index < $submitted + $withdrawn ? 'withdrawn' : 'draft');
                (new Proposal)->forceFill(['project_id' => $project->id, 'user_id' => $freelancer->id, 'status' => $status, 'submitted_at' => $status === 'draft' ? null : now()])->save();
            }

            return $project;
        };
        $none = $receive(0, 0, 6);
        $four = $receive(3, 1, 2);
        $five = $receive(5);
        $nine = $receive(9, 0, 3);
        $ten = $receive(10);
        $nineteen = $receive(18, 1);
        $twenty = $receive(20);
        $more = $receive(22);

        $this->assertSame([$none->id, $four->id], $this->found(['proposals' => '0-4']));
        $this->assertSame([$five->id, $nine->id], $this->found(['proposals' => '5-9']));
        $this->assertSame([$ten->id, $nineteen->id], $this->found(['proposals' => '10-19']));
        $this->assertSame([$twenty->id, $more->id], $this->found(['proposals' => '20']));
        $this->assertCount(8, $this->found(['proposals' => 'any']));
        $this->get('/jobs?proposals=0-4&sort=newest')->assertInertia(fn (Assert $page) => $page->where('filters.proposals', '0-4')->where('projects.total', 2)
            ->where('projects.data.0.id', $four->id)->where('projects.data.0.proposals_received', 4)->where('projects.data.1.proposals_received', 0));
    }

    public function test_closed_jobs_remain_public_but_are_excluded_from_default_feed(): void
    {
        $closed = $this->project(['application_closes_at' => now()->subMinute()]);
        $this->get('/jobs')->assertInertia(fn (Assert $page) => $page->has('projects.data', 0));
        $this->get('/jobs?status=all')->assertInertia(fn (Assert $page) => $page->where('projects.data.0.open', false));
        $this->get('/jobs/'.$closed->id)->assertOk()->assertInertia(fn (Assert $page) => $page->missing('client.email')->where('project.open', false));
        $draft = $this->project(['status' => 'draft']);
        $this->get('/jobs/'.$draft->id)->assertNotFound();
    }

    public function test_invalid_filters_do_not_broaden_search_or_redirect(): void
    {
        foreach (['category=missing', 'skills[]=999999', 'skill_mode=some', 'budget_min=500&budget_max=100', 'sort=sql', 'posted=999', 'proposals=3', 'proposals=20-', 'q[]=x', 'page=-1'] as $query) {
            $this->get('/jobs?'.$query)->assertStatus(422);
        }
    }

    public function test_skill_matching_and_pagination_are_stable(): void
    {
        $skills = Skill::query()->limit(2)->pluck('id')->all();
        $user = User::factory()->create();
        $profile = $user->profile()->create();
        $profile->skillTags()->sync($skills);
        $match = $this->project(['published_at' => now()->subDays(2)], $skills);
        for ($i = 0; $i < 21; $i++) {
            $this->project(['published_at' => now()->subHour()]);
        }
        $this->actingAs($user)->get('/jobs')->assertInertia(fn (Assert $page) => $page->where('filters.sort', 'match')->where('projects.data.0.id', $match->id)->has('projects.data', 20)->where('projects.total', 22));
        $this->get('/jobs?sort=newest&page=2')->assertInertia(fn (Assert $page) => $page->has('projects.data', 2)->where('projects.data.1.id', $match->id));
    }

    public function test_category_directory_uses_active_records_and_real_open_counts(): void
    {
        $project = $this->project();
        $this->project(['category_id' => $project->category_id, 'status' => 'draft']);
        $deleted = Category::create(['categoryname' => 'Removed']);
        $deleted->delete();
        $this->get('/categories?q=development')->assertInertia(fn (Assert $page) => $page->where('categories.data.0.open_projects_count', 1));
        $this->get('/categories?q=removed')->assertInertia(fn (Assert $page) => $page->has('categories.data', 0));
    }
}

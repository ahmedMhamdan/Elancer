<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Project;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectDiscoveryTest extends TestCase
{
    use RefreshDatabase;

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
        $query = http_build_query(['q' => '100%_READY', 'category' => $match->category->slug, 'skills' => $skills, 'budget_min' => 600, 'budget_max' => 900, 'posted' => '7']);
        $this->get('/jobs?'.$query)->assertInertia(fn (Assert $page) => $page->has('projects.data', 1)->where('projects.data.0.id', $match->id));
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
        foreach (['category=missing', 'skills[]=999999', 'budget_min=500&budget_max=100', 'sort=sql', 'posted=999', 'q[]=x', 'page=-1'] as $query) {
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

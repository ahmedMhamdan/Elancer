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

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    private function client(): User
    {
        return User::factory()->create(['workspace_role' => WorkspaceRole::Client, 'onboarding_completed_at' => now()]);
    }

    private function project(User $owner, Category $category, string $title, array $attributes = []): Project
    {
        $project = new Project;
        $project->forceFill(array_merge([
            'user_id' => $owner->id, 'category_id' => $category->id, 'title' => $title, 'description' => 'A complete project brief.',
            'budget_min' => 100, 'budget_max' => 500, 'status' => 'published', 'published_at' => now()->subHour(), 'application_closes_at' => now()->addDays(7),
        ], $attributes))->save();

        return $project;
    }

    private function freelancer(string $name, array $attributes = []): User
    {
        $user = User::factory()->create(['name' => $name, 'workspace_role' => WorkspaceRole::Freelancer, 'onboarding_completed_at' => now()]);
        $profile = $user->profile()->create(['headline' => 'Laravel developer', 'bio' => str_repeat('I build accessible applications. ', 20)]);
        $profile->syncSkillTags(['Laravel']);
        $profile->forceFill(array_merge(['published_at' => now()->subDay()], $attributes))->save();

        return $user;
    }

    public function test_homepage_lists_only_open_public_projects_newest_first(): void
    {
        $client = $this->client();
        $category = Category::create(['categoryname' => 'Development']);
        foreach (range(1, 8) as $age) {
            $this->project($client, $category, 'Open project '.$age, ['published_at' => now()->subDays($age)]);
        }
        $newest = Project::query()->where('title', 'Open project 1')->firstOrFail();
        foreach ([['draft', null], ['submitted', now()]] as [$status, $submitted]) {
            (new Proposal)->forceFill(['project_id' => $newest->id, 'user_id' => $this->freelancer('Applicant '.$status)->id, 'status' => $status, 'submitted_at' => $submitted])->save();
        }
        $suspended = $this->client();
        $suspended->forceFill(['status' => 'suspended'])->save();
        $this->project($client, $category, 'Cutoff passed', ['application_closes_at' => now()->subMinute()]);
        $this->project($client, $category, 'Hired already', ['status' => 'hired']);
        $this->project($client, $category, 'Hidden by moderation', ['moderated_at' => now()]);
        $this->project($client, $category, 'Still a draft', ['status' => 'draft', 'published_at' => null]);
        $this->project($client, $category, 'Scheduled for later', ['published_at' => now()->addDay()]);
        $this->project($suspended, $category, 'Owner suspended');
        $this->project($client, $category, 'In the trash')->delete();

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->component('welcome')
            ->has('projects', 6)->where('totals.projects', 8)
            ->where('projects.0.title', 'Open project 1')->where('projects.5.title', 'Open project 6')
            ->where('projects.0.proposals_received', 1)->where('projects.0.open', true)->where('projects.0.category.categoryname', 'Development')
            ->missing('projects.0.excerpt')->missing('projects.0.description')->missing('projects.0.user_id')->missing('projects.0.user'));
    }

    public function test_homepage_lists_only_public_profiles_and_leads_with_a_photo(): void
    {
        foreach (range(1, 6) as $age) {
            $this->freelancer('Public member '.$age, ['published_at' => now()->subDays($age)]);
        }
        $pictured = $this->freelancer('Pictured member', ['published_at' => now()->subDays(30), 'photo_path' => 'profile-photos/1/photo.jpg']);
        $this->freelancer('Private member', ['published_at' => null]);
        $this->freelancer('Scheduled member', ['published_at' => now()->addDay()]);
        $this->freelancer('Suspended member')->forceFill(['status' => 'suspended'])->save();
        $this->freelancer('Unverified member')->forceFill(['email_verified_at' => null])->save();

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('freelancers', 6)->where('totals.freelancers', 7)
            ->where('freelancers.0.id', $pictured->profile->id)->where('freelancers.0.avatar', route('freelancers.photo', $pictured->profile))
            ->where('freelancers.1.name', 'Public member 1')->where('freelancers.1.avatar', null)->where('freelancers.5.name', 'Public member 5')
            ->where('freelancers.1.bio', fn (string $bio) => mb_strlen($bio) <= 183)
            ->missing('freelancers.0.email')->missing('freelancers.0.photo_path')->missing('freelancers.0.user_id')->missing('freelancers.0.links'));
    }

    public function test_homepage_counts_open_projects_per_category_and_says_nothing_when_there_is_nothing(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->has('projects', 0)->has('freelancers', 0)->has('categories', 0)
            ->where('totals', ['projects' => 0, 'freelancers' => 0, 'categories' => 0]));

        $client = $this->client();
        $quiet = Category::create(['categoryname' => 'Animation']);
        $busy = Category::create(['categoryname' => 'Writing']);
        Category::create(['categoryname' => 'Removed'])->delete();
        $this->project($client, $busy, 'Open one');
        $this->project($client, $busy, 'Open two');
        $this->project($client, $busy, 'Closed', ['application_closes_at' => now()->subDay()]);
        $this->project($client, $busy, 'Hidden', ['moderated_at' => now()]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page->has('categories', 2)->where('totals.categories', 2)
            ->where('categories.0.categoryname', 'Writing')->where('categories.0.open_projects_count', 2)->where('categories.0.slug', $busy->slug)
            ->where('categories.1.categoryname', 'Animation')->where('categories.1.open_projects_count', 0)->where('categories.1.slug', $quiet->slug));
    }
}

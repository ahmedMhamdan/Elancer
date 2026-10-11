<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SavedItemsTest extends TestCase
{
    use RefreshDatabase;

    private function member(array $attributes = []): User
    {
        return User::factory()->create(['onboarding_completed_at' => now(), ...$attributes]);
    }

    private function project(array $overrides = []): Project
    {
        $project = new Project;
        $project->forceFill(array_merge([
            'user_id' => $this->member()->id, 'category_id' => Category::firstOrCreate(['categoryname' => 'Development'])->id,
            'title' => 'Build a booking website', 'description' => str_repeat('A clear project description. ', 3),
            'budget_min' => 100, 'budget_max' => 500, 'status' => 'published',
            'published_at' => now()->subHour(), 'application_closes_at' => now()->addDays(7),
        ], $overrides))->save();

        return $project;
    }

    private function profile(?User $user = null): Profile
    {
        $user ??= $this->member();
        $profile = $user->profile()->create(['headline' => 'Laravel developer', 'bio' => 'I build dependable web applications.']);
        $profile->forceFill(['published_at' => now()->subDay()])->save();
        $profile->skillTags()->sync([Skill::query()->orderBy('id')->value('id')]);

        return $profile;
    }

    public function test_a_saved_project_is_private_to_its_member_and_saved_once(): void
    {
        $member = $this->member();
        $other = $this->member();
        $project = $this->project();

        $this->post('/jobs/'.$project->id.'/save')->assertRedirect('/login');
        $this->actingAs($member)->post('/jobs/'.$project->id.'/save')->assertRedirect();
        $this->post('/jobs/'.$project->id.'/save')->assertRedirect();
        $this->assertSame(1, DB::table('saved_projects')->count());

        $this->get('/saved/projects')->assertInertia(fn (Assert $page) => $page->component('saved/projects')->has('saves.data', 1)
            ->where('saves.data.0.id', $project->id)->where('saves.data.0.project.title', 'Build a booking website')
            ->where('saves.data.0.project.open', true)->where('saves.data.0.project.hired', false)->where('saves.data.0.proposal', null));
        $this->get('/jobs')->assertInertia(fn (Assert $page) => $page->where('projects.data.0.saved', true)->where('projects.data.0.own', false));
        $this->get('/jobs/'.$project->id)->assertInertia(fn (Assert $page) => $page->where('saved', true));

        // Nobody else, signed in or not, can tell that it was saved.
        $this->actingAs($other)->get('/saved/projects')->assertInertia(fn (Assert $page) => $page->has('saves.data', 0));
        $this->get('/jobs')->assertInertia(fn (Assert $page) => $page->where('projects.data.0.saved', false));
        $this->get('/jobs/'.$project->id)->assertInertia(fn (Assert $page) => $page->where('saved', false));
        $this->actingAs($project->user)->get('/jobs/'.$project->id)->assertInertia(fn (Assert $page) => $page->where('saved', false));

        $this->actingAs($other)->delete('/jobs/'.$project->id.'/save')->assertRedirect();
        $this->assertSame(1, DB::table('saved_projects')->count());
        $this->actingAs($member)->delete('/jobs/'.$project->id.'/save')->assertRedirect();
        $this->assertSame(0, DB::table('saved_projects')->count());
    }

    public function test_only_public_projects_of_other_members_can_be_saved_by_members_in_good_standing(): void
    {
        $member = $this->member();
        $this->actingAs($member);

        $this->post('/jobs/'.$this->project(['user_id' => $member->id])->id.'/save')->assertForbidden();
        $this->post('/jobs/'.$this->project(['status' => 'draft'])->id.'/save')->assertNotFound();
        $this->post('/jobs/'.$this->project(['moderated_at' => now()])->id.'/save')->assertNotFound();
        $this->actingAs($this->member(['status' => 'suspended']))->post('/jobs/'.$this->project()->id.'/save')->assertForbidden();
        $this->actingAs(User::factory()->create())->post('/jobs/'.$this->project()->id.'/save')->assertRedirect('/onboarding');
        $this->assertSame(0, DB::table('saved_projects')->count());
    }

    public function test_the_saved_list_shows_closed_hired_applied_and_unavailable_work_honestly(): void
    {
        $member = $this->member();
        $closed = $this->project(['title' => 'Closed work', 'application_closes_at' => now()->subDay()]);
        $hired = $this->project(['title' => 'Hired work', 'status' => 'hired']);
        $applied = $this->project(['title' => 'Applied work']);
        $hidden = $this->project(['title' => 'Hidden work']);
        $this->actingAs($member);
        foreach ([$closed, $hired, $applied, $hidden] as $project) {
            $this->post('/jobs/'.$project->id.'/save')->assertRedirect();
        }
        $proposal = new Proposal;
        $proposal->forceFill(['project_id' => $applied->id, 'user_id' => $member->id, 'status' => 'submitted', 'version' => 1, 'submitted_at' => now(), 'content' => ['price' => '300.00']])->save();
        $hidden->forceFill(['moderated_at' => now()])->save();

        // Newest save first: hidden, applied, hired, closed.
        $this->get('/saved/projects')->assertInertia(fn (Assert $page) => $page->has('saves.data', 4)
            ->where('saves.data.0.id', $hidden->id)->where('saves.data.0.project', null)->where('saves.data.0.proposal', null)
            ->where('saves.data.1.project.title', 'Applied work')->where('saves.data.1.proposal.id', $proposal->id)->where('saves.data.1.proposal.status', 'submitted')
            ->where('saves.data.2.project.hired', true)->where('saves.data.2.project.open', false)
            ->where('saves.data.3.project.hired', false)->where('saves.data.3.project.open', false));

        // A save can be removed after its project left public pages.
        $this->delete('/jobs/'.$hidden->id.'/save')->assertRedirect();
        $this->assertSame(3, DB::table('saved_projects')->where('user_id', $member->id)->count());
    }

    public function test_saved_freelancers_are_private_and_hide_a_profile_that_is_no_longer_public(): void
    {
        $client = $this->member();
        $other = $this->member();
        $profile = $this->profile();

        $this->post('/freelancers/'.$profile->id.'/save')->assertRedirect('/login');
        $this->actingAs($profile->user)->post('/freelancers/'.$profile->id.'/save')->assertForbidden();
        $this->actingAs($client)->post('/freelancers/'.$profile->id.'/save')->assertRedirect();
        $this->post('/freelancers/'.$profile->id.'/save')->assertRedirect();
        $this->assertSame(1, DB::table('saved_profiles')->count());

        $this->get('/saved/freelancers')->assertInertia(fn (Assert $page) => $page->component('saved/freelancers')->has('saves.data', 1)
            ->where('saves.data.0.id', $profile->id)->where('saves.data.0.freelancer.headline', 'Laravel developer'));
        $this->get('/freelancers')->assertInertia(fn (Assert $page) => $page->where('freelancers.data.0.saved', true)->where('freelancers.data.0.own', false));
        $this->get('/freelancers/'.$profile->id)->assertInertia(fn (Assert $page) => $page->where('saved', true));
        $this->actingAs($other)->get('/saved/freelancers')->assertInertia(fn (Assert $page) => $page->has('saves.data', 0));
        $this->get('/freelancers')->assertInertia(fn (Assert $page) => $page->where('freelancers.data.0.saved', false));
        $this->actingAs($profile->user)->get('/freelancers')->assertInertia(fn (Assert $page) => $page->where('freelancers.data.0.saved', false)->where('freelancers.data.0.own', true));

        // Unpublishing removes every detail from the saved list; the row stays so it can be removed.
        $profile->forceFill(['published_at' => null])->save();
        $this->actingAs($client)->post('/freelancers/'.$profile->id.'/save')->assertNotFound();
        $this->get('/saved/freelancers')->assertInertia(fn (Assert $page) => $page->has('saves.data', 1)->where('saves.data.0.freelancer', null));
        $this->delete('/freelancers/'.$profile->id.'/save')->assertRedirect();
        $this->assertSame(0, DB::table('saved_profiles')->count());
    }

    public function test_a_saved_search_keeps_only_the_filters_the_search_accepts_and_reopens_them(): void
    {
        $member = $this->member();
        $category = Category::create(['categoryname' => 'Design']);
        $catalog = Skill::query()->orderBy('id')->take(2)->get();
        $skills = $catalog->pluck('id')->all();
        $filters = ['q' => 'logo', 'category' => $category->slug, 'skills' => $skills, 'skill_mode' => 'all', 'budget_min' => '100', 'budget_max' => '900',
            'posted' => '7', 'proposals' => '0-4', 'status' => 'all', 'sort' => 'newest'];

        $this->post('/saved/searches', ['name' => 'Logo work', 'filters' => $filters])->assertRedirect('/login');
        $this->actingAs($member)->post('/saved/searches', ['name' => 'Logo work', 'filters' => [...$filters, 'page' => 4, 'user_id' => 1]])->assertSessionHasNoErrors();
        $stored = json_decode(DB::table('saved_searches')->where('user_id', $member->id)->value('filters'), true);
        // A database may return a stored object's names in its own order.
        $this->assertEqualsCanonicalizing(['q', 'category', 'skills', 'skill_mode', 'budget_min', 'budget_max', 'posted', 'proposals', 'status', 'sort'], array_keys($stored));
        $this->assertSame($skills, $stored['skills']);

        // Opening it is an ordinary search address, so the results are always current.
        $href = '';
        $this->get('/saved/searches')->assertInertia(function (Assert $page) use (&$href, $catalog): void {
            $page->component('saved/searches')->has('searches.data', 1)->where('searches.data.0.name', 'Logo work')->where('searches.data.0.available', true)
                ->where('searches.data.0.filters.category', 'Design')->where('searches.data.0.filters.skills', $catalog->pluck('name')->all());
            $href = $page->toArray()['props']['searches']['data'][0]['href'];
        });
        $this->get($href)->assertInertia(fn (Assert $page) => $page->component('discovery/jobs')->where('filters.q', 'logo')->where('filters.category', $category->slug)
            ->where('filters.skills', $skills)->where('filters.skill_mode', 'all')->where('filters.budget_min', '100')->where('filters.budget_max', '900')
            ->where('filters.posted', '7')->where('filters.proposals', '0-4')->where('filters.status', 'all')->where('filters.sort', 'newest'));

        // Anything /jobs would refuse is refused here too, and names are unique per member only.
        $this->post('/saved/searches', ['name' => 'Bad budget', 'filters' => ['budget_min' => '500', 'budget_max' => '100']])->assertSessionHasErrors('name');
        $this->post('/saved/searches', ['name' => 'Bad category', 'filters' => ['category' => 'no-such-category']])->assertSessionHasErrors('name');
        $this->post('/saved/searches', ['name' => 'Logo work', 'filters' => []])->assertSessionHasErrors('name');
        $this->post('/saved/searches', ['name' => '', 'filters' => []])->assertSessionHasErrors('name');
        $this->assertSame(1, DB::table('saved_searches')->count());
        $this->actingAs($this->member())->post('/saved/searches', ['name' => 'Logo work', 'filters' => []])->assertSessionHasNoErrors();
        $this->assertSame(2, DB::table('saved_searches')->count());

        // A removed category is never dropped quietly: the search stays but is not offered as a link.
        $category->delete();
        $this->actingAs($member)->get('/saved/searches')->assertInertia(fn (Assert $page) => $page->where('searches.data.0.available', false)->where('searches.data.0.href', null));
    }

    public function test_only_the_owner_renames_or_deletes_a_saved_search_and_the_list_is_bounded(): void
    {
        $member = $this->member();
        $other = $this->member();
        $this->actingAs($member)->post('/saved/searches', ['name' => 'First', 'filters' => []])->assertSessionHasNoErrors();
        $this->post('/saved/searches', ['name' => 'Second', 'filters' => ['q' => 'api']])->assertSessionHasNoErrors();
        $first = DB::table('saved_searches')->where('name', 'First')->value('id');

        $this->actingAs($other)->patch('/saved/searches/'.$first, ['name' => 'Taken over'])->assertNotFound();
        $this->delete('/saved/searches/'.$first)->assertNotFound();
        $this->get('/saved/searches')->assertInertia(fn (Assert $page) => $page->has('searches.data', 0));

        $this->actingAs($member)->patch('/saved/searches/'.$first, ['name' => 'Second'])->assertSessionHasErrors('name');
        $this->patch('/saved/searches/'.$first, ['name' => 'First'])->assertSessionHasNoErrors();
        $this->patch('/saved/searches/'.$first, ['name' => 'Renamed'])->assertSessionHasNoErrors();
        $this->assertSame(['Renamed', 'Second'], DB::table('saved_searches')->where('user_id', $member->id)->orderBy('id')->pluck('name')->all());
        $this->delete('/saved/searches/'.$first)->assertRedirect();
        $this->assertSame(['Second'], DB::table('saved_searches')->where('user_id', $member->id)->pluck('name')->all());

        for ($i = 2; $i <= 25; $i++) {
            DB::table('saved_searches')->insert(['user_id' => $member->id, 'name' => 'Search '.$i, 'filters' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->post('/saved/searches', ['name' => 'One too many', 'filters' => []])->assertSessionHasErrors('name');
        $this->assertSame(25, DB::table('saved_searches')->where('user_id', $member->id)->count());
    }
}

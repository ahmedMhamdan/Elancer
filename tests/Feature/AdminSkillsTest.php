<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PortfolioCase;
use App\Models\Project;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Plan 026: administrators keep the skill catalog; a skill is retired, never deleted. */
class AdminSkillsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(bool $super = false): User
    {
        return User::factory()->withTwoFactor()->create(['is_admin' => true, 'is_super_admin' => $super, 'onboarding_completed_at' => now(), 'name' => 'Ada Admin']);
    }

    /** Signs in with the current-session two-factor proof the admin gate expects. */
    private function signIn(User $user): void
    {
        $this->actingAs($user)->withSession(['admin.two_factor_proof' => $user->id.':'.hash('sha256', (string) $user->two_factor_secret)]);
    }

    private function freelancer(array $skills): User
    {
        $freelancer = User::factory()->create(['onboarding_completed_at' => now()]);
        $profile = $freelancer->profile()->create(['headline' => 'Developer', 'bio' => 'I build bilingual web applications.', 'country' => 'Palestine', 'city' => 'Hebron']);
        $profile->syncSkillTags($skills);
        $profile->forceFill(['published_at' => now()->subDay()])->save();

        return $freelancer;
    }

    private function draft(User $owner, array $skills): Project
    {
        $this->actingAs($owner)->post('/my-projects')->assertRedirect();
        $draft = Project::query()->latest('id')->firstOrFail();
        $draft->skills()->sync($skills);

        return $draft;
    }

    private function events(): array
    {
        return DB::table('moderation_events')->where('target_type', 'skill')->orderBy('id')->get(['action', 'target_id', 'actor_id', 'reason'])->map(fn ($event) => (array) $event)->all();
    }

    public function test_only_administrators_past_two_factor_reach_the_catalog(): void
    {
        $skill = Skill::query()->where('name', 'Laravel')->firstOrFail();
        $this->get('/admin/skills')->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['onboarding_completed_at' => now()]))->get('/admin/skills')->assertForbidden();
        $this->post('/admin/skills', ['name' => 'Elixir'])->assertForbidden();

        // An administrator whose session has not passed two-factor sign-in is shown the gate and changes nothing.
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/skills')->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/categories/security'));
        $this->post('/admin/skills', ['name' => 'Elixir'])->assertForbidden();
        $this->put('/admin/skills/'.$skill->id, ['name' => 'Renamed'])->assertForbidden();
        $this->delete('/admin/skills/'.$skill->id)->assertForbidden();
        $this->post('/admin/skills/'.$skill->id.'/restore')->assertForbidden();
        $this->assertNull(Skill::query()->where('name', 'Elixir')->first());
        $this->assertSame('Laravel', $skill->fresh()->name);
        $this->assertNull($skill->fresh()->retired_at);

        $this->signIn($admin);
        $this->get('/admin/skills')->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/skills/index')->where('status', 'active')->has('skills.data', 20));
        $this->get('/admin/skills/create')->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/skills/form')->where('skill', null));
        $this->get('/admin/skills/'.$skill->id.'/edit')->assertOk()->assertInertia(fn (Assert $page) => $page->where('skill', ['id' => $skill->id, 'name' => 'Laravel']));
    }

    public function test_an_administrator_adds_renames_retires_and_restores_with_a_record_of_each(): void
    {
        $admin = $this->admin();
        $this->signIn($admin);

        // Added: trimmed, inner spaces collapsed, one name whatever the letter case.
        $this->post('/admin/skills', ['name' => "  Rust   Lang \t"])->assertSessionHasNoErrors()->assertRedirect('/admin/skills');
        $skill = Skill::query()->where('name', 'Rust Lang')->firstOrFail();
        foreach (['rust lang', 'LARAVEL', 'R', str_repeat('a', 51), "Zero\u{200B}Width", '', '   '] as $refused) {
            $this->post('/admin/skills', ['name' => $refused])->assertSessionHasErrors('name');
        }
        $this->assertSame(1, Skill::query()->whereRaw('LOWER(name) = ?', ['rust lang'])->count());
        $this->post('/admin/skills', ['name' => 'تحليل البيانات المتقدم'])->assertSessionHasNoErrors();
        $this->post('/admin/skills', ['name' => 'F#'])->assertSessionHasNoErrors();

        // Renamed: the same rules; the same name again is not a change; another skill's name is refused.
        $this->put('/admin/skills/'.$skill->id, ['name' => 'Rust'])->assertSessionHasNoErrors()->assertRedirect('/admin/skills');
        $this->assertSame('Rust', $skill->fresh()->name);
        $this->put('/admin/skills/'.$skill->id, ['name' => ' rust '])->assertSessionHasNoErrors();
        $this->assertSame('rust', $skill->fresh()->name);
        $this->put('/admin/skills/'.$skill->id, ['name' => 'rust'])->assertSessionHasNoErrors();
        $this->put('/admin/skills/'.$skill->id, ['name' => 'laravel'])->assertSessionHasErrors('name');
        $this->assertSame('rust', $skill->fresh()->name);

        // Retired and restored, each once.
        $this->delete('/admin/skills/'.$skill->id)->assertSessionHasNoErrors()->assertStatus(303);
        $this->assertNotNull($skill->fresh()->retired_at);
        $this->delete('/admin/skills/'.$skill->id);
        $this->post('/admin/skills/'.$skill->id.'/restore')->assertSessionHasNoErrors()->assertStatus(303);
        $this->assertNull($skill->fresh()->retired_at);
        $this->post('/admin/skills/'.$skill->id.'/restore');
        $this->delete('/admin/skills/999999')->assertNotFound();

        $actions = array_map(fn (array $event) => [$event['action'], $event['reason']], array_filter($this->events(), fn (array $event) => (int) $event['target_id'] === $skill->id));
        $this->assertSame([['skill_created', null], ['skill_renamed', 'Rust Lang'], ['skill_renamed', 'Rust'], ['skill_retired', null], ['skill_restored', null]], array_values($actions));
        $this->assertSame([$admin->id], array_values(array_unique(array_column($this->events(), 'actor_id'))));

        // The list: counts of what carries a skill, search as plain text, the status filter and the latest changes.
        $laravel = Skill::query()->where('name', 'Laravel')->firstOrFail();
        $this->draft($this->freelancer(['Laravel', 'React']), [$laravel->id]);
        $this->signIn($admin);
        $this->get('/admin/skills?q=LARA')->assertOk()->assertInertia(fn (Assert $page) => $page->has('skills.data', 1)
            ->where('skills.data.0', ['id' => $laravel->id, 'name' => 'Laravel', 'retired' => false, 'profiles' => 1, 'projects' => 1])->where('search', 'LARA'));
        $this->get('/admin/skills?q=%25')->assertInertia(fn (Assert $page) => $page->has('skills.data', 0));
        $this->delete('/admin/skills/'.$laravel->id);
        $this->get('/admin/skills?status=retired')->assertInertia(fn (Assert $page) => $page->has('skills.data', 1)->where('skills.data.0.name', 'Laravel')->where('skills.data.0.retired', true)
            ->where('events.0.action', 'skill_retired')->where('events.0.skill', 'Laravel')->where('events.0.actor', 'Ada Admin')->has('events', 8));
        $this->get('/admin/skills?status=active&q=laravel')->assertInertia(fn (Assert $page) => $page->has('skills.data', 0));
        $this->get('/admin/skills?status=all&q=laravel')->assertInertia(fn (Assert $page) => $page->has('skills.data', 1));
        $this->get('/admin/skills?status=gone')->assertSessionHasErrors('status');
    }

    public function test_a_retired_skill_stays_where_it_is_and_is_no_longer_offered(): void
    {
        $laravel = Skill::query()->where('name', 'Laravel')->firstOrFail();
        $react = Skill::query()->where('name', 'React')->firstOrFail();
        $holder = $this->freelancer(['Laravel', 'React']);
        $newcomer = $this->freelancer(['React']);
        $draft = $this->draft($holder, [$laravel->id]);
        $published = new Project;
        $published->forceFill(['user_id' => $holder->id, 'category_id' => Category::create(['categoryname' => 'Development'])->id, 'title' => 'A Laravel project',
            'description' => 'A complete project brief.', 'budget_min' => 100, 'budget_max' => 500, 'status' => 'published', 'published_at' => now()->subDay(),
            'application_closes_at' => now()->addDays(7)])->save();
        $published->skills()->sync([$laravel->id, $react->id]);
        $case = new PortfolioCase;
        $case->forceFill(['user_id' => $holder->id, 'content' => ['title' => 'Bilingual booking platform', 'summary' => 'A booking platform in English and Arabic for a clinic group.',
            'body' => 'I designed the data model, built the booking flow and delivered a right-to-left interface with tests.', 'skills' => ['Laravel'], 'links' => []]])->save();

        $admin = $this->admin();
        $this->signIn($admin);
        $this->delete('/admin/skills/'.$laravel->id)->assertSessionHasNoErrors();

        // Still on everything that carried it, by name.
        $names = fn (string $path, string $key) => fn (Assert $page) => $page->where($path, fn ($skills) => in_array('Laravel', collect($skills)->pluck($key)->all(), true));
        $this->get('/freelancers/'.$holder->profile->id)->assertOk()->assertInertia($names('freelancer.skills', 'name'));
        $this->get('/jobs/'.$published->id)->assertOk()->assertInertia($names('project.skills', 'name'));
        // An old link or a saved search that names it still searches.
        $this->get('/jobs?skills[]='.$laravel->id)->assertOk()->assertInertia(fn (Assert $page) => $page->has('projects.data', 1));

        // No longer offered: suggestions and both filter lists.
        $offered = fn (Assert $page) => $page->where('skills', fn ($skills) => ! in_array('Laravel', collect($skills)->pluck('name')->all(), true) && in_array('React', collect($skills)->pluck('name')->all(), true));
        $this->actingAs($newcomer)->getJson('/skills?q=lar')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/skills?q=reac')->assertOk()->assertJsonPath('data.0.name', 'React');
        $this->get('/jobs')->assertInertia($offered);
        $this->get('/freelancers')->assertInertia($offered);

        // Cannot be newly chosen anywhere a member picks skills.
        $this->patch('/my-profile', ['skills' => ['React', 'Laravel']])->assertSessionHasErrors('skills.1');
        $this->patchJson('/my-projects/'.$this->draft($newcomer, [])->id, ['version' => 1, 'skills' => [$laravel->id]])->assertUnprocessable()->assertJsonValidationErrors('skills.0');
        $this->post('/my-portfolio', ['title' => 'Bilingual booking platform', 'summary' => 'A booking platform in English and Arabic for a clinic group.',
            'body' => 'I designed the data model, built the booking flow and delivered a right-to-left interface with tests.', 'skills' => ['Laravel'], 'links' => []])->assertSessionHasErrors('skills.0');
        $fresh = User::factory()->create();
        $this->actingAs($fresh)->post('/onboarding', ['role' => 'freelancer', 'name' => 'Nour New', 'headline' => 'Developer', 'bio' => 'Building websites.',
            'skills' => ['Laravel', 'PHP'], 'country' => 'Palestine', 'city' => 'Hebron'])->assertSessionHasErrors('skills.0');

        // The member who already has it keeps it on a save, on the profile, the draft and the case study.
        $this->actingAs($holder)->patch('/my-profile', ['skills' => ['Laravel', 'React', 'PHP']])->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(['Laravel', 'PHP', 'React'], $holder->profile->skillTags()->pluck('name')->all());
        $this->patchJson('/my-projects/'.$draft->id, ['version' => $draft->fresh()->version, 'skills' => [$laravel->id, $react->id]])->assertOk();
        $this->assertEqualsCanonicalizing([$laravel->id, $react->id], $draft->skills()->pluck('skills.id')->all());
        $this->put('/my-portfolio/'.$case->id, ['title' => 'Bilingual booking platform', 'summary' => 'A booking platform in English and Arabic for a clinic group.',
            'body' => 'I designed the data model, built the booking flow and delivered a right-to-left interface with tests.', 'skills' => ['Laravel', 'React'], 'links' => []])->assertSessionHasNoErrors();
        // Once dropped, it cannot be picked again.
        $this->patch('/my-profile', ['skills' => ['React']])->assertSessionHasNoErrors();
        $this->patch('/my-profile', ['skills' => ['React', 'Laravel']])->assertSessionHasErrors('skills.1');

        // Restored, it is offered again.
        $this->signIn($admin);
        $this->post('/admin/skills/'.$laravel->id.'/restore');
        $this->actingAs($newcomer)->getJson('/skills?q=lar')->assertOk()->assertJsonPath('data.0.name', 'Laravel');
        $this->patch('/my-profile', ['skills' => ['React', 'Laravel']])->assertSessionHasNoErrors();
    }
}

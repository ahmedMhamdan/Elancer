<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SkillCatalogTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function onboarding(): array
    {
        return ['role' => 'freelancer', 'name' => 'Skill Tester', 'headline' => 'Developer', 'bio' => 'Building websites.', 'skills' => ['Laravel', 'PHP'], 'country' => 'Palestine', 'city' => 'Hebron'];
    }

    public function test_search_requires_a_verified_account_and_returns_bounded_literal_matches(): void
    {
        $this->getJson('/skills?q=lar')->assertUnauthorized();
        $this->actingAs(User::factory()->unverified()->create())->getJson('/skills?q=lar')->assertForbidden();
        $this->actingAs(User::factory()->create())->getJson('/skills?q=lAr')->assertOk()->assertJsonPath('data.0.name', 'Laravel')->assertJsonCount(1, 'data');
        $this->getJson('/skills')->assertOk()->assertJsonCount(20, 'data');
        $this->getJson('/skills?q=%25')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/skills?q='.urlencode('ترجم'))->assertOk()->assertJsonFragment(['name' => 'ترجمة']);
        $this->getJson('/skills?q='.str_repeat('a', 51))->assertUnprocessable();
    }

    public function test_onboarding_links_existing_tags_and_shows_completion_only_once(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/onboarding', $this->onboarding())->assertSessionHasNoErrors()->assertRedirect('/dashboard');
        $user->refresh();
        $this->assertSame(['Laravel', 'PHP'], $user->fresh()->profile->skillTags()->orderBy('name')->pluck('name')->all());
        $this->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page->where('onboardingReady', true));
        $this->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page->where('onboardingReady', false));
        $this->post('/onboarding', $this->onboarding())->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page->where('onboardingReady', false));
    }

    public function test_unknown_skill_cannot_complete_onboarding_or_create_a_catalog_entry(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/onboarding', [...$this->onboarding(), 'skills' => ['Invented private skill']])->assertSessionHasErrors('skills.0');
        $this->assertNull($user->fresh()->onboarding_completed_at);
        $this->assertDatabaseMissing('skills', ['name' => 'Invented private skill']);
        $this->assertDatabaseCount('profile_skill', 0);
    }

    public function test_profile_replaces_and_clears_own_associations_and_rejects_unknown_tags(): void
    {
        $user = User::factory()->create(['workspace_role' => WorkspaceRole::Freelancer, 'onboarding_completed_at' => now()]);
        $other = User::factory()->create();
        $profile = $other->profile()->create([]);
        $profile->syncSkillTags(['PHP']);
        $this->actingAs($user)->patch('/my-profile', ['skills' => ['React', 'Laravel']])->assertSessionHasNoErrors();
        $this->assertCount(2, $user->fresh()->profile->skillTags);
        $this->patch('/my-profile', ['skills' => ['PHP']])->assertSessionHasNoErrors();
        $this->assertSame(['PHP'], $user->fresh()->profile->skillTags()->pluck('name')->all());
        $this->patch('/my-profile', ['skills' => ['Unknown tag']])->assertSessionHasErrors('skills.0');
        $this->assertSame(['PHP'], $user->fresh()->profile->skills);
        $this->patch('/my-profile', ['skills' => []])->assertSessionHasNoErrors();
        $this->assertSame([], $user->fresh()->profile->skillTags()->pluck('name')->all());
        $this->assertSame(['PHP'], $other->fresh()->profile->skillTags()->pluck('name')->all());
    }

    public function test_additive_migration_preserves_legacy_tags_and_rollback_keeps_json(): void
    {
        $profile = User::factory()->create()->profile()->create([]);
        $profile->forceFill(['skills' => ['Legacy Craft', 'Laravel']])->save();
        $migration = require database_path('migrations/2026_09_13_100000_create_skill_catalog.php');
        $migration->down();
        $migration->up();
        $this->assertSame(['Legacy Craft', 'Laravel'], $profile->fresh()->skills);
        $this->assertSame(['Laravel', 'Legacy Craft'], $profile->skillTags()->orderBy('name')->pluck('name')->all());
        $this->assertSame(1, Skill::where('name', 'Legacy Craft')->count());
        $migration->down();
        $this->assertSame(['Legacy Craft', 'Laravel'], $profile->fresh()->skills);
        $migration->up();
    }
}

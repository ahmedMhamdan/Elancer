<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_sends_null_without_creating_a_profile(): void
    {
        $this->actingAs(User::factory()->create(['onboarding_completed_at' => now()]))->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')->where('profile', null));

        $this->assertDatabaseCount('profiles', 0);
    }

    public function test_dashboard_sends_existing_profile_fields(): void
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $user->profile()->create(['headline' => 'Developer', 'bio' => 'Hello', 'location' => 'Hebron']);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->has('profile', 8)
                ->where('profile.headline', 'Developer')
                ->where('profile.bio', 'Hello')
                ->where('profile.location', 'Hebron')
                ->where('profile.published_at', null));
    }

    public function test_save_creates_then_updates_only_the_signed_in_users_profile(): void
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $otherProfile = User::factory()->create(['onboarding_completed_at' => now()])->profile()->create(['headline' => 'Other owner']);
        $data = ['headline' => 'Developer', 'bio' => 'Hello', 'location' => 'Hebron'];

        $this->actingAs($user)->patch(route('dashboard.profile.update'), $data)
            ->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));

        $profile = $user->profile()->firstOrFail();
        $this->assertSame('Developer', $profile->headline);
        $this->assertSame('Hello', $profile->bio);
        $this->assertSame('Hebron', $profile->location);
        $this->assertNull($profile->published_at);

        $this->patch(route('dashboard.profile.update'), ['headline' => 'Updated', 'bio' => ''])
            ->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));

        $this->assertSame('Updated', $profile->fresh()->headline);
        $this->assertNull($profile->fresh()->bio);
        $this->assertSame('Hebron', $profile->fresh()->location);
        $this->assertSame('Other owner', $otherProfile->fresh()->headline);
        $this->assertDatabaseCount('profiles', 2);
    }

    public function test_invalid_or_server_controlled_fields_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['onboarding_completed_at' => now()]))
            ->from(route('dashboard'))
            ->patch(route('dashboard.profile.update'), [
                'headline' => str_repeat('a', 121),
                'bio' => str_repeat('a', 5001),
                'location' => str_repeat('a', 256),
                'user_id' => 99,
                'published_at' => null,
            ])->assertSessionHasErrors(['headline', 'bio', 'location', 'user_id', 'published_at']);

        $this->assertDatabaseCount('profiles', 0);
    }

    public function test_guests_and_unverified_users_cannot_save(): void
    {
        $this->patch(route('dashboard.profile.update'), ['headline' => 'Blocked'])
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->unverified()->create())
            ->patch(route('dashboard.profile.update'), ['headline' => 'Blocked'])
            ->assertRedirect(route('verification.notice'));

        $this->assertDatabaseCount('profiles', 0);
    }

    public function test_inactive_users_cannot_create_or_update_profiles(): void
    {
        $user = User::factory()->create(['status' => AccountStatus::Suspended, 'onboarding_completed_at' => now()]);

        $this->actingAs($user)->patch(route('dashboard.profile.update'), ['headline' => 'Blocked'])
            ->assertForbidden();
        $this->assertDatabaseCount('profiles', 0);

        $profile = $user->profile()->create(['headline' => 'Original']);
        $this->patch(route('dashboard.profile.update'), ['headline' => 'Blocked'])
            ->assertForbidden();
        $this->assertSame('Original', $profile->fresh()->headline);
    }
}

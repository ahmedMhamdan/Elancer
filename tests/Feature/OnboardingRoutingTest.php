<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OnboardingRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_requires_authentication_and_verified_email(): void
    {
        $this->get(route('onboarding'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('onboarding', ['verified' => 1]))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_incomplete_accounts_reach_onboarding_without_a_redirect_loop(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('onboarding'));
        $this->get(route('onboarding'))->assertInertia(fn (Assert $page) => $page
            ->component('onboarding')->where('initial.name', $user->name)->where('submitUrl', route('onboarding.store'))->missing('preview'));
        $this->assertNull($user->fresh()->onboarding_completed_at);
        $this->assertDatabaseCount('profiles', 0);
    }

    public function test_incomplete_accounts_cannot_bypass_setup_through_profile_writes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->patch(route('dashboard.profile.update'), ['headline' => 'Bypass'])
            ->assertRedirect(route('onboarding'));
        $this->assertDatabaseCount('profiles', 0);
    }

    public function test_completed_accounts_reach_dashboard_and_skip_onboarding(): void
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $this->actingAs($user)->get(route('onboarding'))->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_email_verification_goes_to_onboarding_even_with_an_intended_destination(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id, 'hash' => sha1($user->email),
        ]);
        $this->actingAs($user)->withSession(['url.intended' => '/settings/profile'])
            ->get($url)->assertRedirect(route('onboarding'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->get(route('onboarding'))->assertOk();
    }

    public function test_completed_verification_preserves_the_intended_destination(): void
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id, 'hash' => sha1($user->email),
        ]);
        $this->actingAs($user)->withSession(['url.intended' => '/settings/profile'])
            ->get($url)->assertRedirect('/settings/profile');
    }
}

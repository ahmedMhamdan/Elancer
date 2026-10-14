<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Plan 029: Help is one public page; its articles and its search live in the page itself. */
class HelpPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_help_opens_for_a_guest_and_for_a_member_who_cannot_act_yet(): void
    {
        $this->get('/help')->assertOk()->assertInertia(fn (Assert $page) => $page->component('help/index'));
        // Someone who has not finished setup, or whose account is suspended, is exactly who may need it.
        $this->actingAs(User::factory()->create(['onboarding_completed_at' => null]))->get('/help?q=refund')->assertOk();
        $this->actingAs(User::factory()->create(['onboarding_completed_at' => now(), 'status' => 'suspended']))->get('/help')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('help/index'));
    }
}

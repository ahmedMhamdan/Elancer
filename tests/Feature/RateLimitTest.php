<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_is_counted_per_route_so_autosaves_do_not_use_up_publish(): void
    {
        $owner = User::factory()->create(['onboarding_completed_at' => now()]);
        $this->actingAs($owner)->post('/my-projects')->assertRedirect();
        $draft = Project::query()->latest('id')->firstOrFail();
        $publish = fn () => $this->postJson('/my-projects/'.$draft->id.'/publish', ['version' => $draft->fresh()->version]);

        // Autosave allows 120 a minute and Publish 10; ten saves used to leave Publish refused.
        for ($i = 1; $i <= 10; $i++) {
            $this->patchJson('/my-projects/'.$draft->id, ['version' => $i, 'title' => 'Save '.$i])->assertOk();
        }
        for ($i = 1; $i <= 10; $i++) {
            $publish()->assertUnprocessable();
        }
        // Publish still stops at its own limit, for this member only, and autosave carries on.
        $publish()->assertTooManyRequests();
        $this->patchJson('/my-projects/'.$draft->id, ['version' => 11, 'title' => 'Still saving'])->assertOk();
        $this->actingAs(User::factory()->create(['onboarding_completed_at' => now()]))->postJson('/my-projects/'.$draft->id.'/publish', ['version' => 12])->assertNotFound();
    }

    public function test_a_guest_is_counted_per_route_and_address(): void
    {
        // The filter list allows 60 a minute and finishing a social sign-up 6; without a pending sign-up it answers 419.
        for ($i = 1; $i <= 6; $i++) {
            $this->getJson('/search/filters')->assertOk();
        }
        for ($i = 1; $i <= 6; $i++) {
            $this->postJson('/auth/complete')->assertStatus(419);
        }
        $this->postJson('/auth/complete')->assertTooManyRequests();
        $this->getJson('/search/filters')->assertOk();
    }

    public function test_behind_the_hosts_proxy_each_visitor_has_their_own_guest_counter(): void
    {
        config(['app.client_address_header' => 'CF-Connecting-IP']);
        $finish = fn (string $visitor, array $headers = []) => $this->postJson('/auth/complete', [], ['CF-Connecting-IP' => $visitor, ...$headers]);

        for ($i = 1; $i <= 6; $i++) {
            $finish('198.51.100.7')->assertStatus(419);
        }
        // One visitor reaching the limit no longer refuses the next visitor, and a forwarded chain written by the first does not free them.
        $finish('198.51.100.7')->assertTooManyRequests();
        $finish('198.51.100.7', ['X-Forwarded-For' => '203.0.113.9'])->assertTooManyRequests();
        $finish('203.0.113.9')->assertStatus(419);
    }
}

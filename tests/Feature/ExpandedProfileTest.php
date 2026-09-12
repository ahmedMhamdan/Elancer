<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExpandedProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_separate_profile_page_saves_only_owner_details_and_keeps_draft(): void
    {
        $user = User::factory()->create(['workspace_role' => WorkspaceRole::Freelancer, 'onboarding_completed_at' => now()]);
        $other = User::factory()->create();
        $other->profile()->create(['headline' => 'Unchanged']);
        $this->actingAs($user)->get('/my-profile')->assertInertia(fn (Assert $page) => $page->component('marketplace-profile'));
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->component('dashboard'));
        $this->patch('/my-profile', ['headline' => 'Developer', 'bio' => 'I build applications.', 'country' => 'Palestine', 'city' => 'Hebron', 'skills' => ['Laravel', 'React']])->assertSessionHasNoErrors()->assertRedirect('/my-profile');
        $this->assertSame(['Laravel', 'React'], $user->fresh()->profile->skills);
        $this->assertSame('Hebron, Palestine', $user->fresh()->profile->location);
        $this->assertNull($user->fresh()->profile->published_at);
        $this->assertSame('Unchanged', $other->fresh()->profile->headline);
        $this->patch('/my-profile', ['skills' => ['Laravel', 'laravel'], 'user_id' => $other->id, 'published_at' => now()])->assertSessionHasErrors(['skills.0', 'user_id', 'published_at']);
    }

    public function test_client_company_is_editable_and_guest_cannot_access_profile(): void
    {
        $this->get('/my-profile')->assertRedirect('/login');
        $client = User::factory()->create(['workspace_role' => WorkspaceRole::Client, 'onboarding_completed_at' => now()]);
        $this->actingAs($client)->patch('/my-profile', ['company' => 'Example studio', 'bio' => 'We hire independent talent.', 'country' => 'Palestine', 'city' => 'Hebron'])->assertRedirect('/my-profile');
        $this->assertSame('Example studio', $client->fresh()->profile->company);
    }
}

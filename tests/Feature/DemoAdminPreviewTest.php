<?php

namespace Tests\Feature;

use App\Actions\Auth\DemoAccess;
use App\Actions\Demo\DemoBaseline;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DemoAdminPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function prepared(string $role = 'client'): User
    {
        return User::factory()->create(['email' => $role.'-01@demo.elancer.test', 'workspace_role' => $role, 'onboarding_completed_at' => now()]);
    }

    public function test_the_admin_choice_opens_sample_screens_without_granting_real_permissions(): void
    {
        $private = User::factory()->create(['name' => 'Private member', 'email' => 'private@example.com', 'onboarding_completed_at' => now()]);
        $client = $this->prepared();
        $this->post('/demo/admin')->assertRedirect('/admin/categories');
        $this->assertAuthenticatedAs($client);
        $this->assertTrue(session(DemoAccess::ADMIN_KEY));
        $this->assertFalse($client->fresh()->isAdministrator());
        $this->assertNull(session('admin.two_factor_proof'));

        foreach ([
            '/admin/categories' => 'admin/categories/index', '/admin/categories/create' => 'admin/categories/form', '/admin/categories/1/edit' => 'admin/categories/form',
            '/admin/skills' => 'admin/skills/index', '/admin/skills/create' => 'admin/skills/form', '/admin/skills/1/edit' => 'admin/skills/form',
            '/admin/accounts' => 'admin/accounts/index', '/admin/accounts/1' => 'admin/accounts/show',
            '/admin/reports' => 'admin/reports/index', '/admin/reports/1' => 'admin/reports/show',
            '/admin/administrators' => 'admin/administrators/index', '/admin/administrators/3/edit' => 'admin/administrators/edit',
            '/admin/identity' => 'admin/identity/index', '/admin/operations' => 'admin/operations/index', '/admin/demo' => 'admin/demo/index',
        ] as $url => $component) {
            $response = $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->component($component)->where('auth.demoAdmin', true)
                ->where('auth.user.is_admin', false)->where('auth.user.is_super_admin', false));
            $response->assertDontSee($private->email);
        }
        // Fixture IDs never resolve to matching real models, and records outside the sample do not exist here.
        $this->get('/admin/accounts/1')->assertInertia(fn (Assert $page) => $page->where('account.name', 'Sample super administrator'));
        $this->get('/admin/accounts/999999')->assertNotFound();
        $this->get('/admin/reports/999999')->assertNotFound();
        foreach (['/admin/identity/1/image/id', '/admin/identity/1/image/selfie', '/admin/reports/1/conversation', '/admin/reports/1/files/1'] as $url) {
            $this->get($url)->assertNotFound();
        }
        $this->assertFalse($client->fresh()->isAdministrator());
    }

    public function test_admin_writes_stay_closed_even_when_demo_marketplace_actions_are_enabled(): void
    {
        $client = $this->prepared();
        $member = User::factory()->create();
        $category = Category::query()->create(['categoryname' => 'Real category', 'slug' => 'real-category']);
        DemoBaseline::save(null);
        $this->post('/demo/admin');

        foreach ([
            ['postJson', '/admin/categories', ['categoryname' => 'Taken over']],
            ['deleteJson', '/admin/categories/'.$category->id, []],
            ['putJson', '/admin/administrators/'.$member->id, ['is_admin' => true, 'reason' => 'Attempt to grant privileges']],
            ['patchJson', '/admin/accounts/'.$member->id, ['action' => 'suspend', 'reason' => 'Attempt to suspend', 'member_reason' => 'Attempted from a public demo']],
            ['postJson', '/admin/operations/1/retry', []],
            ['postJson', '/admin/demo/baseline', []],
            ['postJson', '/admin/demo/reset', []],
        ] as [$method, $url, $data]) {
            $this->{$method}($url, $data)->assertForbidden();
        }
        $this->assertFalse($client->fresh()->isAdministrator());
        $this->assertFalse($member->fresh()->isAdministrator());
        $this->assertSame('active', $member->fresh()->status->value);
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'categoryname' => 'Real category', 'deleted_at' => null]);
        $this->assertDatabaseCount('demo_baselines', 1);
    }

    public function test_the_admin_preview_marker_does_not_open_real_admin_tools_or_survive_a_demo_switch(): void
    {
        $client = $this->prepared();
        $this->prepared('freelancer');
        $this->actingAs($client)->withSession([DemoAccess::ADMIN_KEY => true])->get('/admin/accounts')->assertForbidden();
        $this->post('/logout');
        $this->post('/demo/client');
        $this->get('/admin/accounts')->assertForbidden();
        $this->post('/logout');
        $this->post('/demo/admin');
        $this->post('/demo/switch')->assertRedirect('/dashboard');
        $this->assertNull(session(DemoAccess::ADMIN_KEY));
        $this->get('/admin/accounts')->assertForbidden();
    }

    public function test_the_admin_preview_falls_back_only_to_a_safe_prepared_account(): void
    {
        $freelancer = $this->prepared('freelancer');
        $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('demoRoles', ['freelancer', 'admin']));
        $this->post('/demo/admin')->assertRedirect('/admin/categories');
        $this->assertAuthenticatedAs($freelancer);
        $this->post('/logout');
        $freelancer->forceFill(['is_admin' => true])->save();
        $this->post('/demo/admin')->assertSessionHasErrors('demo');
        $this->assertGuest();
    }
}

<?php

namespace Tests\Feature;

use App\Actions\UpdateAdministrator;
use App\Enums\AccountStatus;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class AdministratorManagementTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create([
            'is_super_admin' => true, 'is_admin' => true,
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now(),
        ]);
    }

    private function loginAs(User $user): void
    {
        $this->actingAs($user)->withSession([
            'admin.two_factor_proof' => $user->id.':'.hash('sha256', (string) $user->two_factor_secret),
        ]);
    }

    public function test_super_admin_grants_and_revokes_access_with_an_audit_record(): void
    {
        $actor = $this->superAdmin();
        $target = User::factory()->create();
        $this->loginAs($actor);
        $this->get('/admin/administrators')->assertOk();
        $this->get("/admin/administrators/{$target->id}/edit")->assertOk();
        $this->put("/admin/administrators/{$target->id}", ['is_admin' => true, 'reason' => 'Manage taxonomy'])
            ->assertSessionHasNoErrors()->assertRedirect('/admin/administrators');
        $this->assertTrue($target->fresh()->is_admin);
        $this->assertFalse($target->fresh()->is_super_admin);
        $this->assertDatabaseHas('admin_access_changes', [
            'actor_id' => $actor->id, 'target_id' => $target->id,
            'from_role' => 'member', 'to_role' => 'admin', 'reason' => 'Manage taxonomy',
        ]);
        $this->put("/admin/administrators/{$target->id}", ['is_admin' => true, 'reason' => 'Repeat request'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('admin_access_changes', 1);
        $this->put("/admin/administrators/{$target->id}", ['is_admin' => false, 'reason' => 'Assignment completed'])->assertSessionHasNoErrors();
        $this->assertFalse($target->fresh()->is_admin);
        $this->assertDatabaseCount('admin_access_changes', 2);
        $this->loginAs($target->fresh());
        $this->get('/admin/categories')->assertForbidden();
    }

    public function test_ordinary_admins_members_and_restricted_super_admins_cannot_manage_access(): void
    {
        $target = User::factory()->create();
        $suspended = $this->superAdmin();
        $suspended->forceFill(['status' => AccountStatus::Suspended])->save();
        $deactivated = $this->superAdmin();
        $deactivated->forceFill(['status' => AccountStatus::Deactivated])->save();
        foreach ([User::factory()->create(), User::factory()->withTwoFactor()->create(['is_admin' => true]), $suspended, $deactivated] as $actor) {
            $this->loginAs($actor);
            $this->get('/admin/administrators')->assertForbidden();
            $this->get("/admin/administrators/{$target->id}/edit")->assertForbidden();
            $this->put("/admin/administrators/{$target->id}", ['is_admin' => true, 'reason' => 'Attempt'])->assertForbidden();
        }
        $this->assertFalse($target->fresh()->is_admin);
        $this->assertDatabaseCount('admin_access_changes', 0);
    }

    public function test_guest_verification_and_current_session_two_factor_requirements(): void
    {
        $target = User::factory()->create();
        $this->get('/admin/administrators')->assertRedirect('/login');
        $actor = $this->superAdmin();
        $this->actingAs($actor)->get('/admin/administrators')
            ->assertInertia(fn (Assert $page) => $page->component('admin/categories/security'));
        $this->put("/admin/administrators/{$target->id}", ['is_admin' => true, 'reason' => 'Attempt'])->assertForbidden();
        $this->loginAs($actor);
        $actor->forceFill(['two_factor_confirmed_at' => null])->save();
        $this->put("/admin/administrators/{$target->id}", ['is_admin' => true, 'reason' => 'Attempt'])->assertForbidden();
        $actor->forceFill(['email_verified_at' => null])->save();
        $this->get('/admin/administrators')->assertRedirect('/email/verify');
    }

    public function test_super_admins_cannot_be_changed_or_self_deleted(): void
    {
        $actor = $this->superAdmin();
        $other = $this->superAdmin();
        $this->loginAs($actor);
        foreach ([$actor, $other] as $target) {
            $this->get("/admin/administrators/{$target->id}/edit")->assertForbidden();
            $this->put("/admin/administrators/{$target->id}", ['is_admin' => false, 'reason' => 'Attempt'])->assertForbidden();
        }
        $this->delete('/settings/profile', ['password' => 'password'])->assertSessionHasErrors('password');
        $this->assertNotNull($actor->fresh());
        $this->assertAuthenticatedAs($actor);
    }

    public function test_validation_and_eligibility_cannot_be_bypassed(): void
    {
        $actor = $this->superAdmin();
        $target = User::factory()->unverified()->create();
        $this->loginAs($actor);
        $url = "/admin/administrators/{$target->id}";
        $this->put($url, [])->assertSessionHasErrors(['is_admin', 'reason']);
        $this->put($url, ['is_admin' => 'invalid', 'reason' => str_repeat('a', 501)])->assertSessionHasErrors(['is_admin', 'reason']);
        $this->put($url, ['is_admin' => true, 'reason' => 'Attempt', 'is_super_admin' => true])->assertSessionHasErrors('is_super_admin');
        $this->put($url, ['is_admin' => true, 'reason' => 'Attempt'])->assertSessionHasErrors('is_admin');
        $target->forceFill(['email_verified_at' => now(), 'status' => AccountStatus::Suspended])->save();
        $this->put($url, ['is_admin' => true, 'reason' => 'Attempt'])->assertSessionHasErrors('is_admin');
        $target->forceFill(['is_admin' => true])->save();
        $this->put($url, ['is_admin' => false, 'reason' => 'Remove suspended admin'])->assertSessionHasNoErrors();
        $this->assertFalse($target->fresh()->is_admin);
    }

    public function test_super_admin_defaults_and_mass_assignment_are_protected(): void
    {
        $user = User::factory()->create();
        $this->assertFalse($user->is_super_admin);
        $user->fill(['is_super_admin' => true, 'is_admin' => true])->save();
        $this->assertFalse($user->fresh()->is_super_admin);
        $this->assertFalse($user->fresh()->is_admin);
        $this->actingAs($user)->patch('/settings/profile', [
            'name' => $user->name, 'email' => $user->email, 'is_super_admin' => true,
        ])->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->is_super_admin);
    }

    public function test_search_pagination_and_secret_minimization(): void
    {
        $this->loginAs($this->superAdmin());
        User::factory()->count(11)->create();
        User::factory()->create(['name' => 'Find me', 'email' => 'find@admin.test']);
        $this->get('/admin/administrators')->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 10)->where('users.total', 13)->missing('users.data.0.two_factor_secret')->missing('users.data.0.password'));
        $this->get('/admin/administrators?page=2')->assertInertia(fn (Assert $page) => $page->has('users.data', 3));
        $this->get('/admin/administrators?q=find@admin.test')->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.email', 'find@admin.test'));
        $this->get('/admin/administrators/999/edit')->assertNotFound();
    }

    public function test_role_and_audit_changes_are_atomic_and_history_survives_target_deletion(): void
    {
        $actor = $this->superAdmin();
        $target = User::factory()->create();
        Event::listen('eloquent.updating: '.User::class, function (): void {
            throw new RuntimeException('Simulated write failure');
        });
        try {
            app(UpdateAdministrator::class)($actor, $target, true, 'Atomic change');
            $this->fail('Expected simulated failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated write failure', $exception->getMessage());
        } finally {
            Event::forget('eloquent.updating: '.User::class);
        }
        $this->assertFalse($target->fresh()->is_admin);
        $this->assertDatabaseCount('admin_access_changes', 0);
        app(UpdateAdministrator::class)($actor, $target, true, 'Valid change');
        $target->delete();
        $this->assertDatabaseHas('admin_access_changes', ['target_id' => null, 'target_email' => $target->email, 'reason' => 'Valid change']);
    }

    public function test_bootstrap_command_only_changes_an_explicit_eligible_account_and_is_idempotent(): void
    {
        $user = User::factory()->create();
        $hash = $user->password;
        $this->artisan('app:make-super-admin', ['email' => 'missing@admin.test'])->assertFailed();
        $this->artisan('app:make-super-admin', ['email' => $user->email])->assertSuccessful();
        $this->artisan('app:make-super-admin', ['email' => $user->email])->assertSuccessful();
        $this->assertTrue($user->fresh()->is_super_admin);
        $this->assertTrue($user->fresh()->is_admin);
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertDatabaseCount('admin_access_changes', 1);
        $other = User::factory()->unverified()->create();
        $this->artisan('app:make-super-admin', ['email' => $other->email])->assertFailed();
        $this->assertFalse($other->fresh()->is_super_admin);
    }

    public function test_private_seed_recreates_account_with_same_password_and_is_repeatable(): void
    {
        config(['super_admin.email' => 'owner@seed.test', 'super_admin.password_hash' => Hash::make('private-test-password')]);
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'owner@seed.test')->firstOrFail();
        $this->assertTrue($user->is_super_admin);
        $this->assertTrue(Hash::check('private-test-password', $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->two_factor_secret);
        $this->assertDatabaseCount('admin_access_changes', 1);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_seeding_never_promotes_or_overwrites_an_existing_account(): void
    {
        $user = User::factory()->create();
        $hash = $user->password;
        config(['super_admin.email' => $user->email, 'super_admin.password_hash' => Hash::make('different-password')]);
        $this->seed(SuperAdminSeeder::class);
        $this->assertFalse($user->fresh()->is_super_admin);
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertDatabaseCount('admin_access_changes', 0);
    }
}

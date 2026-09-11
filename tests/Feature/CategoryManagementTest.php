<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'is_admin' => true,
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    private function signInAdmin(?User $user = null): User
    {
        $user ??= $this->admin();
        $this->actingAs($user)->withSession([
            'admin.two_factor_proof' => $user->id.':'.hash('sha256', (string) $user->two_factor_secret),
        ]);

        return $user;
    }

    /** @return array<string, string> */
    private function data(): array
    {
        return ['name_en' => 'Web development', 'name_ar' => 'تطوير الويب', 'slug' => 'web-development'];
    }

    public function test_lifecycle_and_bilingual_persistence(): void
    {
        $this->signInAdmin();
        $this->get('/admin/categories/create')->assertOk();
        $this->post('/admin/categories', $this->data() + ['deleted_at' => now(), 'id' => 900])
            ->assertSessionHasNoErrors()->assertRedirect('/admin/categories');
        $category = Category::firstOrFail();
        $this->assertSame('تطوير الويب', $category->name_ar);
        $this->assertFalse($category->trashed());
        $this->assertNotSame(900, $category->id);
        $this->get("/admin/categories/{$category->id}/edit")->assertOk();
        $this->put("/admin/categories/{$category->id}", array_replace($this->data(), ['name_en' => 'Development']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Development', $category->fresh()->name_en);
        $this->delete("/admin/categories/{$category->id}")->assertRedirect('/admin/categories');
        $this->assertSoftDeleted($category);
        $this->get('/admin/categories')->assertInertia(fn (Assert $page) => $page->has('categories.data', 0));
        $this->get('/admin/categories?status=deleted')->assertInertia(fn (Assert $page) => $page->has('categories.data', 1));
        $this->get("/admin/categories/{$category->id}/edit")->assertNotFound();
        $this->put("/admin/categories/{$category->id}", $this->data())->assertNotFound();
        $this->post('/admin/categories', $this->data())->assertSessionHasErrors('slug');
        $this->post("/admin/categories/{$category->id}/restore")->assertRedirect('/admin/categories?status=deleted');
        $this->assertFalse($category->fresh()->trashed());
        $this->post("/admin/categories/{$category->id}/restore")->assertForbidden();
    }

    public function test_all_endpoints_reject_non_admins_and_restricted_accounts(): void
    {
        $category = Category::create($this->data());
        $deleted = Category::create(array_replace($this->data(), ['slug' => 'deleted']));
        $deleted->delete();
        foreach ([
            User::factory()->create(),
            User::factory()->create(['is_admin' => true, 'status' => AccountStatus::Suspended]),
            User::factory()->create(['is_admin' => true, 'status' => AccountStatus::Deactivated]),
        ] as $user) {
            $this->signInAdmin($user);
            foreach ([
                ['get', '/admin/categories'], ['get', '/admin/categories/create'],
                ['post', '/admin/categories'], ['get', "/admin/categories/{$category->id}/edit"],
                ['put', "/admin/categories/{$category->id}"], ['delete', "/admin/categories/{$category->id}"],
                ['post', "/admin/categories/{$deleted->id}/restore"],
            ] as [$method, $url]) {
                $this->{$method}($url, $method === 'get' ? [] : $this->data())->assertForbidden();
            }
        }
        $this->assertDatabaseCount('categories', 2);
    }

    public function test_guest_and_unverified_are_redirected(): void
    {
        $this->get('/admin/categories')->assertRedirect('/login');
        $this->post('/admin/categories', $this->data())->assertRedirect('/login');
        $user = $this->admin();
        $user->forceFill(['email_verified_at' => null])->save();
        $this->signInAdmin($user);
        $this->get('/admin/categories')->assertRedirect('/email/verify');
        $this->post('/admin/categories', $this->data())->assertRedirect('/email/verify');
    }

    public function test_two_factor_setup_and_session_proof_are_required_and_rotation_invalidates_proof(): void
    {
        $user = $this->admin();
        $this->actingAs($user)->get('/admin/categories')->assertInertia(fn (Assert $page) => $page->component('admin/categories/security')->where('configured', true));
        $this->post('/admin/categories', $this->data())->assertForbidden();
        event(new ValidTwoFactorAuthenticationCodeProvided($user));
        $this->get('/admin/categories')->assertInertia(fn (Assert $page) => $page->component('admin/categories/index'));
        $user->forceFill(['two_factor_secret' => encrypt('ROTATEDSECRET')])->save();
        $this->post('/admin/categories', $this->data())->assertForbidden();
        $this->signInAdmin($user);
        $user->forceFill(['two_factor_confirmed_at' => null])->save();
        $this->get('/admin/categories')->assertInertia(fn (Assert $page) => $page->where('configured', false));
        $this->post('/admin/categories', $this->data())->assertForbidden();
    }

    public function test_invalid_values_and_duplicate_slugs_are_rejected(): void
    {
        $this->signInAdmin();
        $this->post('/admin/categories', [])->assertSessionHasErrors(['name_en', 'name_ar', 'slug']);
        foreach (['Uppercase', 'two words', 'two--hyphens', '-start', 'end-', 'عربي', str_repeat('a', 121)] as $slug) {
            $this->post('/admin/categories', array_replace($this->data(), ['slug' => $slug]))->assertSessionHasErrors('slug');
        }
        $this->post('/admin/categories', array_replace($this->data(), ['name_en' => str_repeat('a', 121), 'name_ar' => ['invalid']]))->assertSessionHasErrors(['name_en', 'name_ar']);
        $category = Category::create($this->data());
        $this->put("/admin/categories/{$category->id}", $this->data())->assertSessionHasNoErrors();
        $other = Category::create(array_replace($this->data(), ['slug' => 'other']));
        $this->put("/admin/categories/{$other->id}", $this->data())->assertSessionHasErrors('slug');
        $this->assertSame('other', $other->fresh()->slug);
    }

    public function test_pagination_filters_and_missing_records(): void
    {
        $this->signInAdmin();
        for ($i = 0; $i < 12; $i++) {
            Category::create(array_replace($this->data(), ['slug' => "category-{$i}"]));
        }
        $this->get('/admin/categories')->assertInertia(fn (Assert $page) => $page->has('categories.data', 10)->where('categories.total', 12));
        $this->get('/admin/categories?page=2')->assertInertia(fn (Assert $page) => $page->has('categories.data', 2));
        $this->get('/admin/categories?status=unknown')->assertSessionHasErrors('status');
        $this->get('/admin/categories?page=-1')->assertSessionHasErrors('page');
        $this->post('/admin/categories/999/restore')->assertNotFound();
        $this->delete('/admin/categories/999')->assertNotFound();
    }

    public function test_database_reserves_deleted_slugs_even_when_validation_is_bypassed(): void
    {
        $category = Category::create($this->data());
        $category->delete();

        $this->expectException(UniqueConstraintViolationException::class);
        Category::create($this->data());
    }

    public function test_arabic_validation_and_another_users_session_proof(): void
    {
        $user = $this->signInAdmin();
        $user->forceFill(['locale' => 'ar'])->save();
        $this->post('/admin/categories', [])->assertSessionHasErrors([
            'name_en' => 'هذا الحقل مطلوب.',
        ]);

        $other = $this->admin();
        $this->actingAs($other)->post('/admin/categories', $this->data())->assertForbidden();
    }
}

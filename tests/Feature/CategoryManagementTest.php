<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        return ['categoryname' => 'Web development'];
    }

    public function test_lifecycle_with_one_name_and_server_owned_slug(): void
    {
        $this->signInAdmin();
        $this->get('/admin/categories/create')->assertOk();
        $this->post('/admin/categories', $this->data() + ['slug' => 'injected', 'deleted_at' => now(), 'id' => 900])
            ->assertSessionHasNoErrors()->assertRedirect('/admin/categories');
        $category = Category::firstOrFail();
        $this->assertSame('Web development', $category->categoryname);
        $this->assertSame('web-development', $category->slug);
        $this->assertFalse($category->trashed());
        $this->assertNotSame(900, $category->id);
        $this->get("/admin/categories/{$category->id}/edit")->assertOk();
        $this->put("/admin/categories/{$category->id}", ['categoryname' => 'تطوير الويب', 'slug' => 'changed'])
            ->assertSessionHasNoErrors();
        $this->assertSame('تطوير الويب', $category->fresh()->categoryname);
        $this->assertSame('web-development', $category->fresh()->slug);
        $this->delete("/admin/categories/{$category->id}")->assertRedirect('/admin/categories');
        $this->assertSoftDeleted($category);
        $this->get('/admin/categories')->assertInertia(fn (Assert $page) => $page->has('categories.data', 0));
        $this->get('/admin/categories?status=deleted')->assertInertia(fn (Assert $page) => $page->has('categories.data', 1));
        $this->get("/admin/categories/{$category->id}/edit")->assertNotFound();
        $this->put("/admin/categories/{$category->id}", $this->data())->assertNotFound();
        $this->post('/admin/categories', $this->data())->assertSessionHasNoErrors();
        $this->assertDatabaseHas('categories', ['slug' => 'web-development-2']);
        $this->post("/admin/categories/{$category->id}/restore")->assertRedirect('/admin/categories?status=deleted');
        $this->assertFalse($category->fresh()->trashed());
        $this->assertSame('web-development', $category->fresh()->slug);
        $this->post("/admin/categories/{$category->id}/restore")->assertForbidden();
    }

    public function test_all_endpoints_reject_non_admins_and_restricted_accounts(): void
    {
        $category = Category::create($this->data());
        $deleted = Category::create(['categoryname' => 'Deleted category']);
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

    public function test_invalid_names_are_rejected_and_old_fields_are_not_a_name(): void
    {
        $this->signInAdmin();
        foreach ([null, '', '   ', ['invalid'], str_repeat('a', 121)] as $name) {
            $this->post('/admin/categories', ['categoryname' => $name])->assertSessionHasErrors('categoryname');
        }
        $this->post('/admin/categories', ['name_en' => 'Old field', 'name_ar' => 'قديم', 'slug' => 'old'])->assertSessionHasErrors('categoryname');
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_automatic_slugs_support_arabic_duplicates_and_empty_slug_bases(): void
    {
        $this->signInAdmin();
        foreach ([
            ['تطوير الويب', 'تطوير-الويب'],
            ['تطوير الويب', 'تطوير-الويب-2'],
            ['Web & Design', 'web-design'],
            ['Web / Design', 'web-design-2'],
            ['!!!', 'category'],
            ['???', 'category-2'],
            [str_repeat('a', 120), str_repeat('a', 100)],
            [str_repeat('a', 120), str_repeat('a', 100).'-2'],
        ] as [$name, $slug]) {
            $this->post('/admin/categories', ['categoryname' => $name])->assertSessionHasNoErrors();
            $this->assertDatabaseHas('categories', ['categoryname' => $name, 'slug' => $slug]);
        }
    }

    public function test_pagination_filters_and_missing_records(): void
    {
        $this->signInAdmin();
        for ($i = 0; $i < 12; $i++) {
            Category::create(['categoryname' => "Category {$i}"]);
        }
        $this->get('/admin/categories')->assertInertia(fn (Assert $page) => $page->has('categories.data', 10)->where('categories.total', 12)->missing('categories.data.0.legacy_name_ar'));
        $this->get('/admin/categories?page=2')->assertInertia(fn (Assert $page) => $page->has('categories.data', 2));
        $this->get('/admin/categories?status=unknown')->assertSessionHasErrors('status');
        $this->get('/admin/categories?page=-1')->assertSessionHasErrors('page');
        $this->post('/admin/categories/999/restore')->assertNotFound();
        $this->delete('/admin/categories/999')->assertNotFound();
    }

    public function test_database_still_rejects_a_reserved_slug(): void
    {
        $category = Category::create($this->data());
        $category->delete();

        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('categories')->insert([
            'categoryname' => 'Other', 'slug' => $category->slug,
        ]);
    }

    public function test_migration_preserves_legacy_names_deleted_rows_and_slugs(): void
    {
        $migration = require database_path('migrations/2026_09_11_200000_use_single_category_name.php');
        $migration->down();
        $id = DB::table('categories')->insertGetId([
            'name_en' => 'Design', 'name_ar' => 'تصميم', 'slug' => 'design', 'deleted_at' => now(),
        ]);
        $migration->up();
        $category = Category::withTrashed()->findOrFail($id);
        $this->assertSame('Design', $category->categoryname);
        $this->assertSame('design', $category->slug);
        $this->assertTrue($category->trashed());
        $this->assertSame('تصميم', $category->getAttribute('legacy_name_ar'));
        $this->assertArrayNotHasKey('legacy_name_ar', $category->toArray());

        $new = Category::create(['categoryname' => 'كتابة']);
        $migration->down();
        $this->assertDatabaseHas('categories', ['id' => $new->id, 'name_en' => 'كتابة', 'name_ar' => 'كتابة']);
        $migration->up();
        $this->assertSame('كتابة', $new->fresh()->categoryname);
    }

    public function test_arabic_validation_and_another_users_session_proof(): void
    {
        $user = $this->signInAdmin();
        $user->forceFill(['locale' => 'ar'])->save();
        $this->post('/admin/categories', [])->assertSessionHasErrors([
            'categoryname' => 'هذا الحقل مطلوب.',
        ]);

        $other = $this->admin();
        $this->actingAs($other)->post('/admin/categories', $this->data())->assertForbidden();
    }

    public function test_permanent_delete_requires_trash_and_removes_the_row(): void
    {
        $this->signInAdmin();
        $category = Category::create($this->data());
        $this->delete("/admin/categories/{$category->id}/permanent")->assertForbidden();
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $category->delete();
        $this->delete("/admin/categories/{$category->id}/permanent")
            ->assertRedirect('/admin/categories?status=deleted')
            ->assertSessionHas('category_notice', 'permanentlyDeleted');
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        $this->post("/admin/categories/{$category->id}/restore")->assertNotFound();
        $this->delete("/admin/categories/{$category->id}/permanent")->assertNotFound();
    }

    public function test_permanent_delete_requires_authorized_admin_and_current_two_factor_proof(): void
    {
        $category = Category::create($this->data());
        $category->delete();
        $url = "/admin/categories/{$category->id}/permanent";
        $this->delete($url)->assertRedirect('/login');
        foreach ([User::factory()->create(), $this->admin()->forceFill(['status' => AccountStatus::Suspended]), $this->admin()->forceFill(['status' => AccountStatus::Deactivated])] as $user) {
            $user->save();
            $this->signInAdmin($user);
            $this->delete($url)->assertForbidden();
        }
        $this->actingAs($this->admin())->withSession(['admin.two_factor_proof' => null]);
        $this->delete($url)->assertForbidden();
        $this->assertSoftDeleted($category);
    }
}

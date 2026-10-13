<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Plan 025: prepared accounts a visitor enters from the login and register pages, in a view-only session. */
class DemoSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('uploads');
    }

    private function prepared(string $role, array $attributes = []): User
    {
        return User::factory()->create(['name' => ucfirst($role).' Demo', 'email' => $role.'-01@demo.elancer.test', 'onboarding_completed_at' => now(),
            'workspace_role' => $role, ...$attributes]);
    }

    /** A project with one stored file, as publication leaves it. */
    private function brief(User $owner, string $status = 'published'): ProjectAttachment
    {
        $project = new Project;
        $project->forceFill(['user_id' => $owner->id, 'category_id' => Category::query()->firstOrCreate(['categoryname' => 'Development'])->id,
            'title' => 'A brief with a file', 'description' => 'A complete project brief.', 'budget_min' => 100, 'budget_max' => 500, 'status' => $status,
            'published_at' => $status === 'draft' ? null : now()->subDay(), 'application_closes_at' => now()->addDays(7)])->save();
        Storage::disk('uploads')->put('project-attachments/'.$project->id.'/file', 'the file');
        $file = new ProjectAttachment;
        $file->forceFill(['project_id' => $project->id, 'name' => 'scope.pdf', 'path' => 'project-attachments/'.$project->id.'/file', 'mime' => 'application/pdf', 'size' => 8])->save();

        return $file;
    }

    public function test_a_button_is_offered_only_for_an_account_a_visitor_may_enter(): void
    {
        $offered = fn (array $roles) => fn (Assert $page) => $page->where('demoRoles', $roles);
        $this->get('/login')->assertInertia($offered([]));
        $this->post('/demo/client')->assertSessionHasErrors('demo');
        $this->assertGuest();

        $client = $this->prepared('client');
        $this->get('/login')->assertInertia($offered(['client']));
        $freelancer = $this->prepared('freelancer');
        $this->get('/login')->assertInertia($offered(['client', 'freelancer']));
        $this->get('/register')->assertInertia($offered(['client', 'freelancer']));
        $this->post('/demo/owner')->assertNotFound();

        // Nothing that could do more than look around, and nothing behind a second factor.
        foreach ([['status' => 'suspended'], ['email_verified_at' => null], ['onboarding_completed_at' => null], ['is_admin' => true], ['is_super_admin' => true],
            ['two_factor_secret' => encrypt('secret')]] as $state) {
            $freelancer->forceFill($state)->save();
            $this->get('/register')->assertInertia($offered(['client']));
            $this->post('/demo/freelancer')->assertSessionHasErrors('demo');
            $this->assertGuest();
            $freelancer->forceFill(['status' => 'active', 'email_verified_at' => now(), 'onboarding_completed_at' => now(), 'is_admin' => false, 'is_super_admin' => false,
                'two_factor_secret' => null])->save();
        }

        config(['demo.enabled' => false]);
        $this->get('/login')->assertInertia($offered([]));
        $this->post('/demo/client')->assertSessionHasErrors('demo');
        $this->assertGuest();
        config(['demo.enabled' => true]);

        // A signed-in member is not moved into the shared account.
        $member = User::factory()->create(['onboarding_completed_at' => now()]);
        $this->actingAs($member)->post('/demo/client')->assertRedirect();
        $this->assertAuthenticatedAs($member);
        $this->assertNull(session('demo'));
        $this->assertSame('client', $client->fresh()->workspace_role->value);
    }

    public function test_a_visitor_enters_without_a_password_in_their_own_language_and_the_buttons_workspace(): void
    {
        // Left in Arabic and in the other workspace by whoever used the shared account last.
        $client = $this->prepared('client', ['locale' => 'ar', 'workspace_role' => 'freelancer']);

        $response = $this->post('/demo/client')->assertRedirect('/dashboard');
        $response->assertCookieMissing(Auth::guard('web')->getRecallerName());
        $this->assertAuthenticatedAs($client);
        $this->assertTrue(session('demo'));
        $this->assertSame('client', $client->fresh()->workspace_role->value);
        $this->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page->where('auth.demo', true)->where('locale', 'en')
            ->where('auth.user.locale', 'en')->where('auth.user.workspace_role', 'client')->missing('auth.user.password'));

        // A language choice stays with the visitor: it is never written to the shared account, and the account's own never shows.
        $this->post('/locale', ['locale' => 'en'])->assertRedirect();
        $this->assertSame('ar', $client->fresh()->locale);
        $this->post('/locale', ['locale' => 'ar'])->assertRedirect();
        $client->forceFill(['locale' => 'en'])->save();
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('locale', 'ar')->where('auth.user.locale', 'ar'));
        $this->assertSame('en', $client->fresh()->locale);
    }

    public function test_a_demo_session_reads_every_page_and_changes_nothing(): void
    {
        $client = $this->prepared('client', ['password' => Hash::make('the-original-password')]);
        $other = $this->brief(User::factory()->create(['onboarding_completed_at' => now()]));
        $this->post('/demo/client')->assertRedirect('/dashboard');
        $before = $client->fresh()->getAttributes();

        foreach (['/dashboard', '/my-projects', '/my-proposals', '/contracts', '/offers', '/messages', '/invitations', '/finance', '/notifications', '/saved/projects',
            '/my-profile', '/jobs', '/jobs/'.$other->project_id, '/freelancers'] as $page) {
            $this->get($page)->assertOk();
        }

        // Refused as a page visit and as a background request alike, before any rule of the action itself is read.
        $refused = 'This is a shared demo account, so changes are switched off. Create your own free account to try it yourself.';
        foreach ([['post', '/my-projects', []], ['patch', '/workspace', ['workspace' => 'freelancer']], ['patch', '/my-profile', ['headline' => 'Changed']],
            ['post', '/jobs/'.$other->project_id.'/save', []], ['post', '/reports', []], ['patch', '/settings/profile', ['name' => 'Taken Over', 'email' => 'mine@example.com']],
            ['put', '/settings/password', ['current_password' => 'the-original-password', 'password' => 'A-new-passw0rd!', 'password_confirmation' => 'A-new-passw0rd!']],
            ['delete', '/settings/profile', ['password' => 'the-original-password']], ['patch', '/settings/notifications', ['messages' => true]],
            ['post', '/user/two-factor-authentication', []], ['post', '/user/confirm-password', ['password' => 'the-original-password']],
            ['post', '/settings/social/google', []], ['post', '/my-profile/identity', []]] as [$method, $address, $data]) {
            $this->from('/dashboard')->{$method}($address, $data)->assertRedirect('/dashboard')->assertStatus(303);
            $this->{$method.'Json'}($address, $data)->assertForbidden()->assertExactJson(['message' => $refused]);
        }
        // Account settings, credentials and provider sign-in are closed as pages too.
        foreach (['/settings', '/settings/profile', '/settings/security', '/settings/appearance', '/settings/notifications', '/user/confirm-password',
            '/user/two-factor-qr-code', '/user/two-factor-recovery-codes', '/auth/google/callback?code=x&state=y'] as $page) {
            $this->get($page)->assertRedirect('/dashboard');
        }

        $this->assertEquals($before, $client->fresh()->getAttributes());
        $this->assertTrue(Hash::check('the-original-password', $client->fresh()->password));
        $this->assertSame(1, Project::query()->count());
        $this->assertSame(0, DB::table('saved_projects')->count());
        $this->assertSame(0, DB::table('reports')->count());
        $this->assertSame(0, DB::table('social_identities')->count());

        // What ends the session or only moves a read mark still works.
        $this->post('/notifications/read')->assertRedirect();
        $this->assertStringNotContainsString('shared demo account', (string) $this->postJson('/broadcasting/auth', ['channel_name' => 'private-nothing', 'socket_id' => '1.1'])->getContent());
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_the_limits_follow_the_session_the_button_opened_not_the_account(): void
    {
        $client = $this->prepared('client');

        // The same account entered with its own credentials keeps everything a member has.
        $this->actingAs($client)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('auth.demo', false));
        $this->patch('/workspace', ['workspace' => 'freelancer'])->assertSessionHasNoErrors();
        $this->assertSame('freelancer', $client->fresh()->workspace_role->value);
        $this->get('/settings/profile')->assertOk();
        $this->post('/locale', ['locale' => 'ar']);
        $this->assertSame('ar', $client->fresh()->locale);
        // "Create your own account" belongs to a demo session only.
        $this->post('/demo/leave')->assertNotFound();
        $this->assertAuthenticatedAs($client);
    }

    public function test_a_briefs_files_stay_with_members_in_a_demo_session(): void
    {
        $theirs = $this->brief(User::factory()->create(['onboarding_completed_at' => now()]));
        $freelancer = $this->prepared('freelancer');
        $own = $this->brief($this->prepared('client'), 'draft');
        $address = fn (ProjectAttachment $file) => '/jobs/'.$file->project_id.'/attachments/'.$file->id;

        // Q11 as it stands for a member who signed in with their own credentials.
        $this->actingAs($freelancer)->get('/jobs/'.$theirs->project_id)->assertInertia(fn (Assert $page) => $page->where('attachments.count', 1)->has('attachments.files', 1));
        $this->get($address($theirs))->assertOk();
        $this->post('/logout');

        // The same account through the button: the count everyone sees, and nothing more.
        $this->post('/demo/freelancer')->assertRedirect('/dashboard');
        $this->get('/jobs/'.$theirs->project_id)->assertOk()->assertInertia(fn (Assert $page) => $page->where('attachments.count', 1)->where('attachments.files', null));
        $this->get($address($theirs))->assertForbidden();
        $this->post('/logout');

        // The demo account's own brief stays readable to its own demo session.
        $this->post('/demo/client')->assertRedirect('/dashboard');
        $this->get($address($own))->assertOk();
    }

    public function test_create_your_own_account_ends_the_demo_session_and_opens_registration(): void
    {
        $this->prepared('client');
        $this->post('/demo/client')->assertRedirect('/dashboard');
        $this->post('/locale', ['locale' => 'ar']);

        $this->post('/demo/leave')->assertRedirect('/register');
        $this->assertGuest();
        $this->assertNull(session('demo'));
        $this->get('/register')->assertOk()->assertInertia(fn (Assert $page) => $page->where('locale', 'ar')->where('auth.user', null));
    }
}

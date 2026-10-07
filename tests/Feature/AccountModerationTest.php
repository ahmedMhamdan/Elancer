<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Offer;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountModerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function member(string $name = 'Mona Member'): User
    {
        return User::factory()->create(['onboarding_completed_at' => now(), 'name' => $name]);
    }

    private function admin(bool $super = false): User
    {
        return User::factory()->withTwoFactor()->create(['is_admin' => true, 'is_super_admin' => $super, 'onboarding_completed_at' => now()]);
    }

    /** Signs in with the current-session two-factor proof the admin gate expects. */
    private function signIn(User $user): void
    {
        $this->actingAs($user)->withSession(['admin.two_factor_proof' => $user->id.':'.hash('sha256', (string) $user->two_factor_secret)]);
    }

    /** @return array<string, mixed> */
    private function suspension(array $extra = []): array
    {
        return ['action' => 'suspend', 'reason' => 'Three confirmed reports of off-platform payment requests.',
            'member_reason' => 'Asking members to pay outside Elancer breaks our rules.', ...$extra];
    }

    private function reportAbout(User $subject, User $reporter): Report
    {
        $report = new Report;
        $report->forceFill(['reporter_id' => $reporter->id, 'target_type' => 'profile', 'target_id' => 1, 'subject_id' => $subject->id,
            'reason' => 'off_platform', 'explanation' => 'They asked me to pay outside Elancer.', 'snapshot' => ['title' => $subject->name],
            'status' => 'submitted', 'open_key' => $reporter->id.':profile:'.$subject->id])->save();

        return $report;
    }

    public function test_only_administrators_with_two_factor_proof_manage_ordinary_members(): void
    {
        $member = $this->member();
        $url = '/admin/accounts/'.$member->id;

        $this->get('/admin/accounts')->assertRedirect('/login');
        $this->actingAs($this->member('Other Member'));
        $this->get('/admin/accounts')->assertForbidden();
        $this->get($url)->assertForbidden();
        $this->patch($url, $this->suspension())->assertForbidden();

        // An administrator without this session's two-factor proof gets the security page and changes nothing.
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->get($url)->assertInertia(fn (Assert $page) => $page->component('admin/categories/security'));
        $this->patch($url, $this->suspension())->assertForbidden();
        $this->assertSame('active', $member->fresh()->status->value);

        // The lookup finds by name or address and never sends secrets.
        $this->signIn($admin);
        $this->get('/admin/accounts?q=Mona')->assertInertia(fn (Assert $page) => $page->component('admin/accounts/index')->has('users.data', 1)
            ->where('users.data.0.email', $member->email)->where('users.data.0.status', 'active')
            ->missing('users.data.0.password')->missing('users.data.0.two_factor_secret')->missing('users.data.0.remember_token'));
        $this->get('/admin/accounts?status=suspended')->assertInertia(fn (Assert $page) => $page->has('users.data', 0));
        $this->get($url)->assertInertia(fn (Assert $page) => $page->component('admin/accounts/show')->where('can.suspend', true)->where('can.reinstate', false)
            ->where('account.email', $member->email)->has('events', 0)->missing('account.password')->missing('account.two_factor_secret'));

        // Both reasons are required.
        $this->patch($url, $this->suspension(['member_reason' => null]))->assertSessionHasErrors('member_reason');
        $this->patch($url, $this->suspension(['reason' => 'No']))->assertSessionHasErrors('reason');
        $this->patch($url, ['action' => 'delete', 'reason' => 'Not an action.'])->assertSessionHasErrors('action');

        // Q69: never an administrator, a super administrator or the actor's own account.
        $other = $this->admin();
        $super = $this->admin(super: true);
        foreach ([$other, $super, $admin] as $protected) {
            $this->get('/admin/accounts/'.$protected->id)->assertInertia(fn (Assert $page) => $page->where('can.suspend', false)->where('can.reinstate', false));
            $this->patch('/admin/accounts/'.$protected->id, $this->suspension())->assertForbidden();
            $this->assertSame('active', $protected->fresh()->status->value);
        }
        // A deactivated account is not something to suspend or reinstate.
        $closed = $this->member('Closed Member');
        $closed->forceFill(['status' => 'deactivated'])->save();
        $this->patch('/admin/accounts/'.$closed->id, $this->suspension())->assertConflict();
        $this->patch('/admin/accounts/'.$closed->id, ['action' => 'reinstate', 'reason' => 'Trying to reopen it.'])->assertConflict();
        $this->assertDatabaseCount('moderation_events', 0);

        // A super administrator suspends an ordinary member too; a suspended administrator loses the screen.
        $this->signIn($super);
        $this->patch($url, $this->suspension())->assertRedirect($url);
        $this->assertSame('suspended', $member->fresh()->status->value);
        $other->forceFill(['status' => 'suspended'])->save();
        $this->signIn($other);
        $this->get($url)->assertForbidden();
        $this->patch($url, ['action' => 'reinstate', 'reason' => 'A suspended administrator trying.'])->assertForbidden();
        $this->assertSame('suspended', $member->fresh()->status->value);
    }

    public function test_suspending_closes_pending_offers_tells_the_member_and_is_audited_and_reinstating_revives_nothing(): void
    {
        $client = $this->member('Clara Client');
        $freelancer = $this->member('Farah Freelancer');
        $project = new Project;
        $project->forceFill(['user_id' => $client->id, 'category_id' => Category::create(['categoryname' => 'Development'])->id,
            'title' => 'Build an application', 'description' => 'A real project brief.', 'budget_min' => 100, 'budget_max' => 500,
            'status' => 'published', 'published_at' => now()->subDay(), 'application_closes_at' => now()->subHour()])->save();
        $proposal = new Proposal;
        $proposal->forceFill(['project_id' => $project->id, 'user_id' => $freelancer->id, 'status' => 'submitted', 'version' => 1,
            'content' => ['price' => '300.00', 'duration_days' => 7], 'submitted_at' => now()->subHours(2)])->save();
        $this->actingAs($client)->post('/proposals/'.$proposal->id.'/offer', ['scope' => 'Build a bilingual application with the agreed account and project workflows.',
            'deliverables' => ['Application source code'], 'amount' => '750.25', 'duration_days' => 14, 'revision_rounds' => 1,
            'proposal_version' => 1, 'client_token' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $offer = Offer::query()->firstOrFail();
        $admin = $this->admin();
        $url = '/admin/accounts/'.$freelancer->id;
        $report = $this->reportAbout($freelancer, $client);

        // A report is named only when it is about this member and open to this administrator.
        $this->signIn($admin);
        $this->patch($url, $this->suspension(['report' => $this->reportAbout($client, $freelancer)->id]))->assertNotFound();
        $this->patch($url, $this->suspension(['report' => $this->reportAbout($freelancer, $admin)->id]))->assertNotFound();
        $this->patch($url, $this->suspension(['report' => 999999]))->assertNotFound();
        $this->assertSame('active', $freelancer->fresh()->status->value);
        $this->get($url.'?report='.$report->id)->assertInertia(fn (Assert $page) => $page->where('report', $report->id)->where('account.reports', 1));
        $this->get('/admin/accounts/'.$client->id.'?report='.$report->id)->assertInertia(fn (Assert $page) => $page->where('report', null));

        $this->patch($url, $this->suspension(['report' => $report->id]))->assertRedirect($url.'?report='.$report->id);
        $suspended = $freelancer->fresh();
        $this->assertSame(['suspended', 'Asking members to pay outside Elancer breaks our rules.'], [$suspended->status->value, $suspended->suspension_reason]);
        $this->assertNotNull($suspended->suspended_at);
        // Q70: the pending offer is closed with a reason and its slot is free.
        $this->assertSame(['restricted', 'account_restricted', null], [$offer->fresh()->status, $offer->fresh()->reason, $offer->fresh()->pending_project_id]);
        $this->assertDatabaseHas('moderation_events', ['action' => 'account_suspended', 'actor_id' => $admin->id, 'subject_id' => $freelancer->id,
            'target_type' => 'account', 'target_id' => $freelancer->id, 'report_id' => $report->id,
            'reason' => 'Three confirmed reports of off-platform payment requests.', 'member_reason' => 'Asking members to pay outside Elancer breaks our rules.']);
        // A second suspension changes nothing, and the report's own log shows the action.
        $this->patch($url, $this->suspension())->assertConflict();
        $this->get('/admin/reports/'.$report->id)->assertInertia(fn (Assert $page) => $page->where('events.0.action', 'account_suspended')
            ->where('report.subject.id', $freelancer->id)->where('report.subject.status', 'suspended'));
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('can.suspend', false)->where('can.reinstate', true)
            ->where('events.0.action', 'account_suspended')->where('events.0.actor', $admin->name)->where('events.0.report_id', $report->id));

        // The member is told the member-facing reason and never the internal one.
        $response = $this->actingAs($suspended)->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.status', 'suspended')->where('auth.user.suspension_reason', 'Asking members to pay outside Elancer breaks our rules.'));
        $this->assertStringNotContainsString('Three confirmed reports', $response->getContent());
        $this->patch('/offers/'.$offer->id, ['action' => 'accept', 'version' => 1])->assertSessionHasErrors('offer');
        $this->get('/freelancers')->assertOk();

        // Reinstating needs a reason, clears what the member was told and revives no offer.
        $this->signIn($admin);
        $this->patch($url, ['action' => 'reinstate'])->assertSessionHasErrors('reason');
        $this->patch($url, ['action' => 'reinstate', 'reason' => 'The member agreed to follow the rules.'])->assertRedirect($url);
        $reinstated = $freelancer->fresh();
        $this->assertSame(['active', null, null], [$reinstated->status->value, $reinstated->suspension_reason, $reinstated->suspended_at]);
        $this->patch($url, ['action' => 'reinstate', 'reason' => 'The member agreed to follow the rules.'])->assertConflict();
        $this->assertSame('restricted', $offer->fresh()->status);
        $this->assertSame(['account_suspended', 'account_reinstated'], DB::table('moderation_events')->where('subject_id', $freelancer->id)->orderBy('id')->pluck('action')->all());
        $this->get($url)->assertInertia(fn (Assert $page) => $page->has('events', 2)->where('events.0.action', 'account_reinstated')
            ->where('events.1.member_reason', 'Asking members to pay outside Elancer breaks our rules.')->where('account.suspension_reason', null));
        $this->actingAs($freelancer)->patch('/offers/'.$offer->id, ['action' => 'accept', 'version' => 2])->assertSessionHasErrors('offer');
        // Q70: after reinstatement the client can send a fresh offer.
        $this->actingAs($client)->post('/proposals/'.$proposal->id.'/offer', ['scope' => 'Build a bilingual application with the agreed account and project workflows.',
            'deliverables' => ['Application source code'], 'amount' => '750.25', 'duration_days' => 14, 'revision_rounds' => 1,
            'proposal_version' => 1, 'client_token' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->assertSame(1, Offer::query()->where('status', 'pending')->count());
    }
}

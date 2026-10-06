<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Contract;
use App\Models\ConversationMessage;
use App\Models\Offer;
use App\Models\PortfolioCase;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A client and a freelancer with a public profile, a visible project, a public case study,
     * a contract and one message from the freelancer in its conversation.
     *
     * @return array{User, User, Contract, PortfolioCase, ConversationMessage}
     */
    private function marketplace(): array
    {
        $freelancer = User::factory()->create(['onboarding_completed_at' => now(), 'name' => 'Farah Freelancer']);
        $profile = $freelancer->profile()->create(['headline' => 'Laravel developer', 'bio' => 'I build bilingual web applications.']);
        $profile->syncSkillTags(['Laravel']);
        $profile->forceFill(['published_at' => now()])->save();
        $client = User::factory()->create(['onboarding_completed_at' => now(), 'name' => 'Clara Client']);
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
        $this->actingAs($freelancer)->patch('/offers/'.Offer::query()->latest('id')->value('id'), ['action' => 'accept', 'version' => 1])->assertSessionHasNoErrors();
        $contract = Contract::query()->latest('id')->firstOrFail();
        $case = new PortfolioCase;
        $content = ['title' => 'Bilingual booking platform', 'summary' => 'A booking platform in English and Arabic.', 'body' => 'I designed the data model and built the booking flow.', 'skills' => ['Laravel'], 'links' => []];
        $case->forceFill(['user_id' => $freelancer->id, 'content' => $content, 'public_content' => $content, 'published_at' => now()])->save();
        $message = new ConversationMessage;
        $message->forceFill(['conversation_id' => $contract->conversation_id, 'sender_id' => $freelancer->id, 'body' => 'Pay me outside the site.', 'client_token' => (string) Str::uuid()])->save();

        return [$client, $freelancer, $contract, $case, $message];
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

    private function report(string $type, int $id, array $extra = []): TestResponse
    {
        return $this->post('/reports', ['target_type' => $type, 'target_id' => $id, 'reason' => 'off_platform', 'explanation' => 'They asked me to pay outside Elancer.', ...$extra]);
    }

    public function test_a_member_reports_only_what_they_can_see_and_follows_it_without_private_details(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        [$client, $freelancer, $contract, $case, $message] = $this->marketplace();
        $project = Project::query()->firstOrFail();
        $stranger = User::factory()->create(['onboarding_completed_at' => now()]);

        $this->post('/logout');
        $this->report('project', $project->id)->assertRedirect('/login');

        // Public things are reportable by any member; private ones only by their participants.
        $this->actingAs($stranger);
        $this->report('project', $project->id)->assertSessionHasNoErrors();
        $this->report('profile', $freelancer->profile->id)->assertSessionHasNoErrors();
        $this->report('case', $case->id)->assertSessionHasNoErrors();
        $this->report('message', $message->id)->assertNotFound();
        $this->report('contract', $contract->id)->assertNotFound();
        $this->report('project', 999999)->assertNotFound();
        $this->report('review', 1)->assertSessionHasErrors('target_type');
        $this->report('project', $project->id, ['reason' => 'boring'])->assertSessionHasErrors('reason');
        $this->report('profile', $freelancer->profile->id, ['explanation' => 'Too short'])->assertSessionHasErrors('explanation');
        $this->assertDatabaseCount('reports', 3);

        // One open report per member per target; another member may still report the same thing.
        $this->report('project', $project->id)->assertSessionHasErrors('report');
        $this->actingAs($client);
        $this->report('project', $project->id)->assertSessionHasErrors('report');
        $this->report('message', $message->id)->assertSessionHasNoErrors();
        $this->report('contract', $contract->id)->assertSessionHasNoErrors();
        $this->report('profile', $freelancer->profile->id)->assertSessionHasNoErrors();
        $this->actingAs($freelancer);
        $this->report('message', $message->id)->assertSessionHasErrors('report');
        $this->report('profile', $freelancer->profile->id)->assertSessionHasErrors('report');
        $this->report('case', $case->id)->assertSessionHasErrors('report');
        $this->assertDatabaseCount('reports', 6);

        // A report never changes the reported thing, and what is no longer visible cannot be reported.
        $this->assertSame('Pay me outside the site.', $message->fresh()->body);
        $this->get('/jobs/'.$project->id)->assertOk();
        $case->forceFill(['hidden_at' => now()])->save();
        $this->actingAs($client)->report('case', $case->id)->assertNotFound();

        $mine = Report::query()->where('reporter_id', $client->id)->where('target_type', 'message')->firstOrFail();
        $this->assertSame($freelancer->id, $mine->subject_id);
        $this->assertSame($contract->conversation_id, $mine->conversation_id);
        $this->assertSame($freelancer->id, Report::query()->where('target_type', 'contract')->value('subject_id'));
        $this->assertArrayNotHasKey('text', $mine->snapshot);

        // Q66: the reporter sees their own text, the status and the outcome, nothing else.
        $admin = $this->admin();
        $mine->forceFill(['status' => 'resolved', 'outcome' => 'action_taken', 'handler_id' => $admin->id, 'resolved_at' => now(), 'open_key' => null])->save();
        DB::table('report_notes')->insert(['report_id' => $mine->id, 'author_id' => $admin->id, 'body' => 'Private reviewer note', 'created_at' => now()]);
        $response = $this->actingAs($client)->get('/my-reports')->assertInertia(fn (Assert $page) => $page->component('reports/index')->has('reports.data', 3)
            ->where('reports.data.2.id', $mine->id)->where('reports.data.2.status', 'resolved')->where('reports.data.2.outcome', 'action_taken')
            ->where('reports.data.2.explanation', 'They asked me to pay outside Elancer.')->where('reports.data.2.title', 'Build an application')
            ->missing('reports.data.2.handler_id')->missing('reports.data.2.subject_id')->missing('reports.data.2.snapshot')->missing('reports.data.2.conversation_id'));
        $this->assertStringNotContainsString('Private reviewer note', $response->getContent());
        $this->assertStringNotContainsString($admin->name, $response->getContent());
        $this->actingAs($stranger)->get('/my-reports')->assertInertia(fn (Assert $page) => $page->has('reports.data', 3)->where('reports.data.0.target_type', 'case'));

        // A resolved report frees the slot, so the same thing can be reported again with new details.
        $this->actingAs($client)->report('message', $message->id)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('reports', 7);
    }

    public function test_reports_are_rate_limited_per_member(): void
    {
        $this->actingAs(User::factory()->create(['onboarding_completed_at' => now()]));
        foreach (range(1, 5) as $attempt) {
            $this->report('project', 999999)->assertNotFound();
        }
        $this->report('project', 999999)->assertTooManyRequests();
    }

    public function test_only_administrators_with_two_factor_proof_reach_the_queue(): void
    {
        [$client, , , , $message] = $this->marketplace();
        $this->actingAs($client)->report('message', $message->id)->assertSessionHasNoErrors();
        $report = Report::query()->firstOrFail();
        $paths = ['/admin/reports', '/admin/reports/'.$report->id, '/admin/reports/'.$report->id.'/conversation'];

        $this->post('/logout');
        $this->get('/admin/reports')->assertRedirect('/login');
        $this->actingAs($client);
        foreach ($paths as $path) {
            $this->get($path)->assertForbidden();
        }
        $this->patch('/admin/reports/'.$report->id, ['action' => 'start'])->assertForbidden();
        $this->post('/admin/reports/'.$report->id.'/notes', ['body' => 'A note'])->assertForbidden();

        // An administrator without this session's two-factor proof gets the security page and no data.
        $admin = $this->admin();
        $this->actingAs($admin);
        foreach ($paths as $path) {
            $this->get($path)->assertInertia(fn (Assert $page) => $page->component('admin/categories/security'));
        }
        $this->patch('/admin/reports/'.$report->id, ['action' => 'start'])->assertForbidden();
        $this->assertSame('submitted', $report->fresh()->status);
        $this->assertDatabaseCount('moderation_events', 0);

        // Regular and super administrators both work the queue.
        foreach ([$admin, $this->admin(super: true)] as $actor) {
            $this->signIn($actor);
            $this->get('/admin/reports')->assertInertia(fn (Assert $page) => $page->component('admin/reports/index')->has('reports.data', 1)
                ->where('reports.data.0.reporter', 'Clara Client')->where('reports.data.0.subject', 'Farah Freelancer')->where('reports.data.0.title', 'Build an application'));
            $this->get('/admin/reports/'.$report->id)->assertInertia(fn (Assert $page) => $page->component('admin/reports/show')
                ->where('target.text', 'Pay me outside the site.')->where('can.start', true)->where('can.conversation', false));
        }
    }

    public function test_a_report_is_reviewed_by_one_administrator_at_a_time_and_every_step_is_audited(): void
    {
        [$client, $freelancer, $contract, , $message] = $this->marketplace();
        $this->actingAs($client)->report('message', $message->id)->assertSessionHasNoErrors();
        $this->report('contract', $contract->id)->assertSessionHasNoErrors();
        $report = Report::query()->where('target_type', 'message')->firstOrFail();
        $url = '/admin/reports/'.$report->id;
        DB::table('message_revisions')->insert(['conversation_message_id' => $message->id, 'body' => 'An earlier wording.', 'version' => 1, 'created_at' => now()]);
        [$first, $second] = [$this->admin(), $this->admin()];

        // Q65: no conversation access before the administrator is the reviewer.
        $this->signIn($first);
        $this->get($url.'/conversation')->assertForbidden();
        $this->patch($url, ['action' => 'resolve', 'outcome' => 'action_taken', 'reason' => 'Confirmed in the thread.'])->assertConflict();
        $this->patch($url, ['action' => 'start'])->assertRedirect($url);
        $this->patch($url, ['action' => 'start'])->assertConflict();
        $this->assertSame(['in_review', $first->id], [$report->fresh()->status, $report->fresh()->handler_id]);
        $this->actingAs($client)->get('/my-reports')->assertInertia(fn (Assert $page) => $page->where('reports.data.1.status', 'in_review')->where('reports.data.1.outcome', null));

        // The reviewer reads the whole reported conversation, and each load is one audit row.
        $this->signIn($first);
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('can.resolve', true)->where('can.conversation', true)
            ->where('target.revisions.0.body', 'An earlier wording.')->where('report.subject_reports', 1));
        $this->get($url.'/conversation')->assertInertia(fn (Assert $page) => $page->component('admin/reports/conversation')
            ->where('report.reported_message', $message->id)->where('messages.data.0.body', 'Pay me outside the site.')
            ->where('messages.data.0.sender', 'freelancer')->where('messages.data.0.revisions.0.body', 'An earlier wording.')->where('conversation.client', 'Clara Client'));
        $this->get($url.'/conversation')->assertOk();
        $this->assertSame(2, DB::table('moderation_events')->where(['action' => 'conversation_read', 'actor_id' => $first->id, 'report_id' => $report->id, 'conversation_id' => $contract->conversation_id])->count());

        // Another administrator cannot read or resolve until they take the review over.
        $this->signIn($second);
        $this->get($url.'/conversation')->assertForbidden();
        $this->patch($url, ['action' => 'resolve', 'outcome' => 'action_taken', 'reason' => 'Confirmed in the thread.'])->assertConflict();
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('can.take', true)->where('can.resolve', false)->where('can.conversation', false));
        $this->post($url.'/notes', ['body' => 'I can take this one.'])->assertRedirect($url);
        $this->patch($url, ['action' => 'start'])->assertRedirect($url);
        $this->signIn($first);
        $this->get($url.'/conversation')->assertForbidden();

        $this->signIn($second);
        $this->patch($url, ['action' => 'resolve', 'outcome' => 'made_up', 'reason' => 'Confirmed in the thread.'])->assertSessionHasErrors('outcome');
        $this->patch($url, ['action' => 'resolve', 'outcome' => 'action_taken'])->assertSessionHasErrors('reason');
        $this->patch($url, ['action' => 'resolve', 'outcome' => 'action_taken', 'reason' => 'Confirmed in the thread.'])->assertRedirect($url);
        $resolved = $report->fresh();
        $this->assertSame(['resolved', 'action_taken', null], [$resolved->status, $resolved->outcome, $resolved->open_key]);
        // A resolved report closes the reviewer's access to the conversation.
        $this->get($url.'/conversation')->assertForbidden();
        $this->patch($url, ['action' => 'start'])->assertConflict();

        $this->assertSame(['review_started', 'conversation_read', 'conversation_read', 'note_added', 'review_taken_over', 'resolved'],
            DB::table('moderation_events')->where('report_id', $report->id)->orderBy('id')->pluck('action')->all());
        $this->assertDatabaseHas('moderation_events', ['action' => 'resolved', 'actor_id' => $second->id, 'subject_id' => $freelancer->id, 'reason' => 'Confirmed in the thread.']);
        // The audit log names the conversation and never carries message text.
        $this->assertStringNotContainsString('outside the site', (string) json_encode(DB::table('moderation_events')->get()));

        // Q28: resolving changed nothing about the contract or the message.
        $this->assertSame('awaiting_payment', $contract->fresh()->status);
        $this->assertSame('Pay me outside the site.', $message->fresh()->body);
        $this->get('/admin/reports?status=resolved')->assertInertia(fn (Assert $page) => $page->has('reports.data', 1)->where('reports.data.0.id', $report->id));

        // The contract report shows the agreement and the participants, with no files or payment details.
        $other = Report::query()->where('target_type', 'contract')->firstOrFail();
        $this->get('/admin/reports/'.$other->id)->assertInertia(fn (Assert $page) => $page->where('target.client', 'Clara Client')->where('target.freelancer', 'Farah Freelancer')
            ->where('target.amount', '750.25')->missing('target.files')->missing('target.payment'));
    }

    public function test_an_administrator_cannot_handle_a_report_they_filed_or_one_about_them(): void
    {
        [, $freelancer, , $case] = $this->marketplace();
        $admin = $this->admin();
        $freelancer->forceFill(['is_admin' => true, 'two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($admin)->report('case', $case->id)->assertSessionHasNoErrors();
        $report = Report::query()->firstOrFail();

        foreach ([$admin, $freelancer->fresh()] as $involved) {
            $this->signIn($involved);
            $this->get('/admin/reports')->assertInertia(fn (Assert $page) => $page->has('reports.data', 0));
            $this->get('/admin/reports/'.$report->id)->assertNotFound();
            $this->patch('/admin/reports/'.$report->id, ['action' => 'start'])->assertNotFound();
            $this->post('/admin/reports/'.$report->id.'/notes', ['body' => 'A note'])->assertNotFound();
        }
        $this->assertSame('submitted', $report->fresh()->status);
        $this->assertDatabaseCount('moderation_events', 0);

        $this->signIn($this->admin());
        $this->get('/admin/reports/'.$report->id)->assertInertia(fn (Assert $page) => $page->where('report.snapshot.title', 'Bilingual booking platform')
            ->where('target.href', '/portfolio/'.$case->id)->where('report.reporter.name', $admin->name));
    }
}

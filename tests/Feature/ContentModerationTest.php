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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Q68, Q84: hiding and restoring reported content from its report. */
class ContentModerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function member(string $name): User
    {
        return User::factory()->create(['onboarding_completed_at' => now(), 'name' => $name]);
    }

    private function freelancer(string $name): User
    {
        $freelancer = $this->member($name);
        $profile = $freelancer->profile()->create(['headline' => 'Laravel developer', 'bio' => 'I build bilingual web applications.']);
        $profile->syncSkillTags(['Laravel']);
        $profile->forceFill(['published_at' => now()])->save();

        return $freelancer;
    }

    private function project(User $client, string $title, bool $open): Project
    {
        $project = new Project;
        $project->forceFill(['user_id' => $client->id, 'category_id' => Category::query()->firstOrCreate(['categoryname' => 'Development'])->id,
            'title' => $title, 'description' => 'A real project brief.', 'budget_min' => 100, 'budget_max' => 500,
            'status' => 'published', 'published_at' => now()->subDay(), 'application_closes_at' => $open ? now()->addDay() : now()->subHour()])->save();

        return $project;
    }

    private function proposal(Project $project, User $freelancer): Proposal
    {
        $proposal = new Proposal;
        $proposal->forceFill(['project_id' => $project->id, 'user_id' => $freelancer->id, 'status' => 'submitted', 'version' => 1,
            'content' => ['price' => '300.00', 'duration_days' => 7], 'submitted_at' => now()->subHours(2)])->save();

        return $proposal;
    }

    private function offer(User $client, Proposal $proposal): TestResponse
    {
        return $this->actingAs($client)->post('/proposals/'.$proposal->id.'/offer', ['scope' => 'Build a bilingual application with the agreed account and project workflows.',
            'deliverables' => ['Application source code'], 'amount' => '750.25', 'duration_days' => 14, 'revision_rounds' => 1,
            'proposal_version' => 1, 'client_token' => (string) Str::uuid()]);
    }

    private function admin(): User
    {
        return User::factory()->withTwoFactor()->create(['is_admin' => true, 'onboarding_completed_at' => now()]);
    }

    /** Signs in with the current-session two-factor proof the admin gate expects. */
    private function signIn(User $user): void
    {
        $this->actingAs($user)->withSession(['admin.two_factor_proof' => $user->id.':'.hash('sha256', (string) $user->two_factor_secret)]);
    }

    private function report(string $type, int $id): Report
    {
        $this->post('/reports', ['target_type' => $type, 'target_id' => $id, 'reason' => 'inappropriate', 'explanation' => 'This breaks the Elancer rules.'])->assertSessionHasNoErrors();

        return Report::query()->latest('id')->firstOrFail();
    }

    public function test_a_hidden_message_reaches_no_participant_and_is_restored_as_it_was(): void
    {
        $client = $this->member('Clara Client');
        $freelancer = $this->freelancer('Farah Freelancer');
        $proposal = $this->proposal($this->project($client, 'Build an application', false), $freelancer);
        $this->offer($client, $proposal)->assertSessionHasNoErrors();
        $this->actingAs($freelancer)->patch('/offers/'.Offer::query()->value('id'), ['action' => 'accept', 'version' => 1])->assertSessionHasNoErrors();
        $contract = Contract::query()->firstOrFail();
        $thread = '/messages/'.$contract->conversation_id;
        $message = new ConversationMessage;
        $message->forceFill(['conversation_id' => $contract->conversation_id, 'sender_id' => $freelancer->id, 'body' => 'Pay me outside the site.',
            'client_token' => (string) Str::uuid(), 'version' => 2, 'edited_at' => now()])->save();
        DB::table('message_revisions')->insert(['conversation_message_id' => $message->id, 'body' => 'An earlier wording.', 'version' => 1, 'created_at' => now()]);
        $this->actingAs($freelancer)->get($thread)->assertInertia(fn (Assert $page) => $page->where('messages.data.0.can_edit', true)->where('messages.data.0.hidden', false));
        $report = $this->actingAs($client)->report('message', $message->id);
        $url = '/admin/reports/'.$report->id;
        $hide = ['action' => 'hide', 'reason' => 'Asks for payment outside Elancer.'];

        // Only the administrator whose review is in progress hides content.
        $this->patch($url, $hide)->assertForbidden();
        [$first, $second] = [$this->admin(), $this->admin()];
        $this->actingAs($first)->patch($url, $hide)->assertForbidden();
        $this->signIn($first);
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('moderation.hidden', false)->where('can.hide', false)->where('can.restore', false));
        $this->patch($url, $hide)->assertConflict();
        $this->patch($url, ['action' => 'start'])->assertRedirect($url);
        $this->patch($url, ['action' => 'hide'])->assertSessionHasErrors('reason');
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('can.hide', true)->where('can.restore', false));
        $this->patch($url, $hide)->assertRedirect($url);
        $this->patch($url, $hide)->assertConflict();
        $hidden = $message->fresh();
        $this->assertSame(['Pay me outside the site.', 2, $first->id], [$hidden->body, $hidden->version, $hidden->moderated_by]);
        $this->assertNotNull($hidden->moderated_at);

        // The reviewer keeps the evidence; the page says who hid it.
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('moderation.hidden', true)->where('moderation.by', $first->name)
            ->where('can.hide', false)->where('can.restore', true)->where('target.text', 'Pay me outside the site.')->where('target.hidden', true)
            ->where('target.revisions.0.body', 'An earlier wording.')->where('events.0.action', 'content_hidden'));
        $this->get($url.'/conversation')->assertInertia(fn (Assert $page) => $page->where('messages.data.0.hidden', true)->where('messages.data.0.body', 'Pay me outside the site.'));

        // Neither participant receives the text or its history anywhere, and it cannot be corrected or reported.
        foreach ([$client, $freelancer] as $participant) {
            $page = $this->actingAs($participant)->get($thread)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('messages.data.0.hidden', true)->where('messages.data.0.body', null)->where('messages.data.0.edited_at', null)
                ->where('messages.data.0.can_edit', false)->has('messages.data.0.revisions', 0)
                ->where('conversation.preview', '')->where('conversation.preview_hidden', true));
            foreach ([$page, $this->get('/messages')->assertOk(), $this->get('/dashboard')->assertOk(), $this->get('/contracts/'.$contract->id)->assertOk(), $this->get('/notifications')->assertOk()] as $response) {
                $this->assertStringNotContainsString('outside the site', $response->getContent());
                $this->assertStringNotContainsString('earlier wording', $response->getContent());
            }
        }
        $this->actingAs($freelancer)->patch('/messages/items/'.$message->id, ['body' => 'A different wording now.', 'version' => 2])->assertConflict();
        $this->actingAs($client)->post('/reports', ['target_type' => 'message', 'target_id' => $message->id, 'reason' => 'other', 'explanation' => 'Reporting the hidden message again.'])->assertNotFound();
        $this->assertSame('Pay me outside the site.', $message->fresh()->body);

        // One audit row, with the reason and without message text.
        $this->assertDatabaseHas('moderation_events', ['action' => 'content_hidden', 'actor_id' => $first->id, 'report_id' => $report->id,
            'target_type' => 'message', 'target_id' => $message->id, 'subject_id' => $freelancer->id, 'reason' => 'Asks for payment outside Elancer.']);
        $this->assertStringNotContainsString('outside the site', (string) json_encode(DB::table('moderation_events')->get()));

        // Another administrator waits for the review to end; after resolution a restore is open to them.
        $restore = ['action' => 'restore', 'reason' => 'Hidden by mistake; the wording is allowed.'];
        $this->signIn($second);
        $this->patch($url, $restore)->assertConflict();
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('can.restore', false)->where('target.text', 'Pay me outside the site.'));
        $this->travel(20)->minutes();
        $this->signIn($first);
        $this->patch($url, ['action' => 'resolve', 'outcome' => 'action_taken', 'reason' => 'Message hidden.'])->assertRedirect($url);
        $this->signIn($second);
        $this->patch($url, ['action' => 'restore'])->assertSessionHasErrors('reason');
        $this->patch($url, $restore)->assertRedirect($url);
        $this->patch($url, $restore)->assertConflict();
        // A resolved report cannot hide again; that needs a new report and review.
        $this->patch($url, $hide)->assertConflict();
        $restored = $message->fresh();
        $this->assertSame(['Pay me outside the site.', 2, null, null], [$restored->body, $restored->version, $restored->moderated_at, $restored->moderated_by]);
        $this->assertTrue($restored->edited_at->equalTo($hidden->edited_at));

        // Restored as it was, and the 15-minute correction window is not reopened.
        $this->actingAs($freelancer)->get($thread)->assertInertia(fn (Assert $page) => $page->where('messages.data.0.hidden', false)
            ->where('messages.data.0.body', 'Pay me outside the site.')->where('messages.data.0.revisions.0.body', 'An earlier wording.')
            ->where('messages.data.0.can_edit', false)->where('conversation.preview', 'Pay me outside the site.')->where('conversation.preview_hidden', false));
        $this->patch('/messages/items/'.$message->id, ['body' => 'A different wording now.', 'version' => 2])->assertConflict();
        $this->assertSame(['review_started', 'content_hidden', 'conversation_read', 'resolved', 'content_restored'], DB::table('moderation_events')->where('report_id', $report->id)->orderBy('id')->pluck('action')->all());
        // Q28: nothing happened to the contract.
        $this->assertSame('awaiting_payment', $contract->fresh()->status);
    }

    public function test_a_hidden_project_or_case_study_leaves_public_pages_while_existing_work_continues(): void
    {
        $client = $this->member('Clara Client');
        $freelancer = $this->freelancer('Farah Freelancer');
        $project = $this->project($client, 'Open project with a questionable brief', true);
        $proposal = $this->proposal($project, $freelancer);
        $content = ['title' => 'Bilingual booking platform', 'summary' => 'A booking platform in English and Arabic.', 'body' => 'I designed the data model and built the booking flow.', 'skills' => ['Laravel'], 'links' => []];
        $case = new PortfolioCase;
        $case->forceFill(['user_id' => $freelancer->id, 'content' => $content, 'public_content' => $content, 'published_at' => now()])->save();
        $stranger = $this->freelancer('Sami Stranger');
        $this->actingAs($stranger);
        [$aboutProject, $aboutCase, $aboutProfile] = [$this->report('project', $project->id), $this->report('case', $case->id), $this->report('profile', $freelancer->profile->id)];
        $admin = $this->admin();
        $hide = ['action' => 'hide', 'reason' => 'The public text breaks the content rules.'];
        $restore = ['action' => 'restore', 'reason' => 'The owner corrected the text.'];
        $listed = fn () => $this->get('/jobs')->viewData('page')['props']['projects']['data'];

        // A profile cannot be hidden; suspension is the action for an account.
        $this->signIn($admin);
        foreach ([$aboutProject, $aboutCase, $aboutProfile] as $report) {
            $this->patch('/admin/reports/'.$report->id, ['action' => 'start'])->assertRedirect();
        }
        $this->patch('/admin/reports/'.$aboutProfile->id, $hide)->assertNotFound();
        $this->get('/admin/reports/'.$aboutProfile->id)->assertInertia(fn (Assert $page) => $page->where('moderation', null)->where('can.hide', false));
        $this->assertCount(1, $listed());

        // Project: gone from the list and from its direct link for everyone but its owner.
        $this->patch('/admin/reports/'.$aboutProject->id, $hide)->assertRedirect();
        $this->assertNotNull($project->fresh()->moderated_at);
        $this->assertTrue($project->fresh()->getAttribute('updated_at')->equalTo($project->getAttribute('updated_at')));
        $this->get('/admin/reports/'.$aboutProject->id)->assertInertia(fn (Assert $page) => $page->where('moderation.hidden', true)
            ->where('target.href', null)->where('target.title', 'Open project with a questionable brief')->where('can.restore', true));
        $this->assertCount(0, $listed());
        $this->post('/logout');
        $this->get('/jobs/'.$project->id)->assertNotFound();
        $this->actingAs($stranger)->get('/jobs/'.$project->id)->assertNotFound();
        $this->get('/jobs/'.$project->id.'/apply')->assertConflict();
        $this->putJson('/jobs/'.$project->id.'/proposal', ['action' => 'save', 'version' => 0])->assertConflict();
        $this->post('/reports', ['target_type' => 'project', 'target_id' => $project->id, 'reason' => 'other', 'explanation' => 'Reporting the hidden project again.'])->assertNotFound();
        $this->actingAs($client)->get('/jobs/'.$project->id)->assertOk()->assertInertia(fn (Assert $page) => $page->where('moderated', true)->where('application.owner', true)->where('project.open', false));
        $this->get('/my-projects')->assertInertia(fn (Assert $page) => $page->where('projects.data', fn (Collection $rows) => $rows->count() === 1
            && $rows[0]['moderated_at'] !== null && ! array_key_exists('moderated_by', $rows[0])));
        $this->post('/freelancers/'.$stranger->profile->id.'/invite', ['project_id' => $project->id])->assertUnprocessable();

        // Hiring already under way continues: the applicant revises, the client offers, the freelancer accepts.
        $this->actingAs($freelancer)->get('/jobs/'.$project->id.'/apply')->assertOk();
        $this->get('/proposals/'.$proposal->id)->assertOk();
        $this->offer($client, $proposal)->assertSessionHasNoErrors();
        $this->actingAs($freelancer)->patch('/offers/'.Offer::query()->value('id'), ['action' => 'accept', 'version' => 1])->assertSessionHasNoErrors();
        $this->assertSame(1, Contract::query()->where('project_id', $project->id)->count());
        $this->get('/contracts/'.Contract::query()->value('id'))->assertOk();

        // Restoring puts the project back as it was.
        $this->signIn($admin);
        $this->patch('/admin/reports/'.$aboutProject->id, $restore)->assertRedirect();
        $this->assertSame([null, null], [$project->fresh()->moderated_at, $project->fresh()->moderated_by]);
        $this->actingAs($stranger)->get('/jobs/'.$project->id)->assertOk()->assertInertia(fn (Assert $page) => $page->where('moderated', false));

        // Case study: gone from its page and the public profile; nothing the owner does shows it again.
        $this->get('/portfolio/'.$case->id)->assertOk();
        $this->signIn($admin);
        $this->patch('/admin/reports/'.$aboutCase->id, $hide)->assertRedirect();
        $this->get('/admin/reports/'.$aboutCase->id)->assertInertia(fn (Assert $page) => $page->where('moderation.hidden', true)
            ->where('target.text', 'I designed the data model and built the booking flow.')->where('target.href', null));
        $this->actingAs($stranger)->get('/portfolio/'.$case->id)->assertNotFound();
        $this->get('/freelancers/'.$freelancer->profile->id)->assertInertia(fn (Assert $page) => $page->has('cases', 0));
        $this->post('/reports', ['target_type' => 'case', 'target_id' => $case->id, 'reason' => 'other', 'explanation' => 'Reporting the hidden case study again.'])->assertNotFound();
        $this->actingAs($freelancer)->get('/portfolio/'.$case->id)->assertNotFound();
        $this->get('/my-portfolio')->assertInertia(fn (Assert $page) => $page->where('cases.0.moderated', true)->missing('cases.0.moderated_by'));
        $this->get('/my-portfolio/'.$case->id.'/edit')->assertInertia(fn (Assert $page) => $page->where('case.moderated', true));
        foreach (['hide', 'show', 'publish'] as $action) {
            $this->patch('/my-portfolio/'.$case->id, ['action' => $action])->assertSessionHasNoErrors();
        }
        $this->get('/portfolio/'.$case->id)->assertNotFound();
        $this->assertNotNull($case->fresh()->moderated_at);

        $this->signIn($admin);
        $this->patch('/admin/reports/'.$aboutCase->id, $restore)->assertRedirect();
        $this->actingAs($stranger)->get('/portfolio/'.$case->id)->assertOk();
        $this->get('/freelancers/'.$freelancer->profile->id)->assertInertia(fn (Assert $page) => $page->has('cases', 1));
        $this->assertSame(['content_hidden', 'content_restored', 'content_hidden', 'content_restored'],
            DB::table('moderation_events')->whereIn('action', ['content_hidden', 'content_restored'])->orderBy('id')->pluck('action')->all());
        $this->assertDatabaseHas('moderation_events', ['action' => 'content_hidden', 'target_type' => 'case', 'target_id' => $case->id, 'subject_id' => $freelancer->id, 'report_id' => $aboutCase->id]);
    }
}

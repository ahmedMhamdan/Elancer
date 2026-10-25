<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Models\Category;
use App\Models\Invitation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    private function member(bool $published = true): User
    {
        $user = User::factory()->create(['workspace_role' => WorkspaceRole::Freelancer, 'onboarding_completed_at' => now()]);
        $profile = $user->profile()->create(['headline' => 'Laravel developer', 'bio' => 'Building reliable applications.']);
        $profile->syncSkillTags(['Laravel']);
        $profile->forceFill(['published_at' => $published ? now() : null])->save();

        return $user;
    }

    private function project(User $owner): Project
    {
        $project = new Project;
        $project->forceFill(['user_id' => $owner->id, 'category_id' => Category::create(['categoryname' => 'Development'])->id,
            'title' => 'Build a website', 'description' => str_repeat('A complete specification. ', 5), 'budget_min' => 100, 'budget_max' => 500,
            'status' => 'published', 'published_at' => now()->subHour(), 'application_closes_at' => now()->addDays(20), 'screening_questions' => []])->save();

        return $project;
    }

    private function invite(User $owner, User $recipient, Project $project): Invitation
    {
        $this->actingAs($owner)->post('/freelancers/'.$recipient->profile->id.'/invite', ['project_id' => $project->id])->assertRedirect();

        return Invitation::query()->where('project_id', $project->id)->where('recipient_id', $recipient->id)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function proposal(int $version = 0, string $action = 'submit'): array
    {
        return ['action' => $action, 'version' => $version, 'price' => 200, 'duration_days' => 7, 'message' => 'I will implement and test this application.', 'answers' => [], 'samples' => []];
    }

    public function test_invitation_is_private_and_does_not_grant_chat_or_disclose_account_data(): void
    {
        $owner = $this->member();
        $recipient = $this->member();
        $other = $this->member();
        $project = $this->project($owner);
        $invitation = $this->invite($owner, $recipient, $project);
        $this->actingAs($other)->get('/invitations/'.$invitation->id)->assertNotFound();
        $this->get('/invitations')->assertInertia(fn (Assert $page) => $page->has('invitations.data', 0));
        $this->actingAs($recipient)->get('/invitations/'.$invitation->id)->assertInertia(fn (Assert $page) => $page->component('invitations/index')->where('box', 'received')
            ->where('invitation.status', 'pending')->where('invitation.state', 'waiting')->missing('invitation.email')->missing('invitation.recipient_id')
            ->missing('invitation.person.email')->where('invitation.person.company', null)->where('invitation.can_respond', true));
        $this->get('/invitations')->assertInertia(fn (Assert $page) => $page->has('invitations.data', 1));
        $this->actingAs($owner)->get('/invitations?box=sent')->assertInertia(fn (Assert $page) => $page->has('invitations.data', 1));
        $this->assertDatabaseCount('invitation_events', 1);
    }

    public function test_a_client_opens_on_sent_invitations_and_a_freelancer_on_received(): void
    {
        $owner = $this->member();
        $recipient = $this->member();
        $this->invite($owner, $recipient, $this->project($owner));
        $owner->forceFill(['workspace_role' => WorkspaceRole::Client])->save();
        $this->actingAs($owner)->get('/invitations')->assertInertia(fn (Assert $page) => $page->where('box', 'sent')->has('invitations.data', 1));
        $this->get('/invitations?box=received')->assertInertia(fn (Assert $page) => $page->where('box', 'received')->has('invitations.data', 0));
        $this->actingAs($recipient)->get('/invitations')->assertInertia(fn (Assert $page) => $page->where('box', 'received')->has('invitations.data', 1));
    }

    /**
     * Plan 032 part 14: the inbox lists invitations by where they stand. A pending invitation waits only while its project
     * takes applications; afterwards, like one a block cancelled, it is closed. The lists add up to All, waiting ones first.
     */
    public function test_inbox_lists_invitations_by_where_they_stand(): void
    {
        $this->freezeTime();
        $owner = $this->member();
        $owner->forceFill(['workspace_role' => WorkspaceRole::Client])->save();
        $owner->profile->forceFill(['company' => 'Olive Studio', 'country' => 'Jordan'])->save();
        [$waiting, $applied, $declined, $blocked, $late] = [$this->member(), $this->member(), $this->member(), $this->member(), $this->member()];
        $open = $this->project($owner);
        $closing = $open->replicate(['title']);
        $closing->forceFill(['title' => 'Closing soon', 'application_closes_at' => now()->addDay()])->save();
        $this->invite($owner, $waiting, $open);
        $this->invite($owner, $applied, $open);
        $toDecline = $this->invite($owner, $declined, $open);
        $this->invite($owner, $blocked, $open);
        $this->travel(1)->minutes();
        $lateInvitation = $this->invite($owner, $late, $closing);
        $this->actingAs($applied)->putJson('/jobs/'.$open->id.'/proposal', $this->proposal())->assertOk();
        $this->actingAs($declined)->patch('/invitations/'.$toDecline->id, ['action' => 'decline', 'version' => 1])->assertRedirect();
        $this->actingAs($blocked)->post('/freelancers/'.$owner->profile->id.'/block')->assertRedirect();
        $this->travel(2)->days();

        $this->actingAs($owner)->get('/invitations')->assertInertia(fn (Assert $page) => $page->where('box', 'sent')->where('list', 'all')
            ->where('counts', ['all' => 5, 'waiting' => 1, 'applied' => 1, 'declined' => 1, 'closed' => 2])->where('other', 0)
            ->where('invitations.data.0.state', 'waiting')->where('invitations.data.0.counterpart.name', $waiting->name)
            ->where('invitations.data.1.id', $lateInvitation->id)->where('invitations.data.1.state', 'closed')
            ->where('invitation.state', 'waiting')->where('invitation.project.budget_min', '100.00')->where('preview', true));
        $this->get('/invitations?list=waiting')->assertInertia(fn (Assert $page) => $page->has('invitations.data', 1)->where('invitations.data.0.state', 'waiting'));
        $this->get('/invitations?list=closed')->assertInertia(fn (Assert $page) => $page->has('invitations.data', 2));
        $acceptedId = Invitation::query()->where('recipient_id', $applied->id)->value('id');
        $this->get('/invitations/'.$acceptedId)->assertInertia(fn (Assert $page) => $page->where('preview', false)->where('invitation.state', 'applied')
            ->where('invitation.proposal.price', '200.00')->where('invitation.person.profile', $applied->profile->id)->where('invitation.person.completed', 0)
            ->where('invitation.project.budget_min', '100.00')->where('invitation.public', true)->where('invitation.can_resend', false));
        // Seven days after the decline the client may invite again.
        $this->travel(5)->days();
        $this->get('/invitations/'.$toDecline->id)->assertInertia(fn (Assert $page) => $page->where('invitation.state', 'declined')->where('invitation.can_resend', true));

        // The freelancer whose project closed reads it as closed, with nothing left to answer, and Overview no longer counts it.
        $this->actingAs($late)->get('/invitations')->assertInertia(fn (Assert $page) => $page->where('box', 'received')->where('counts.waiting', 0)
            ->where('invitation.state', 'closed')->where('invitation.can_respond', false)->where('invitation.can_decline', false)
            ->where('invitation.person.company', 'Olive Studio')->where('invitation.person.avatar', null)->where('invitation.person.profile', null));
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('overview.counts.invitations', 0));
        $this->actingAs($waiting)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('overview.counts.invitations', 1));
        $this->get('/invitations')->assertInertia(fn (Assert $page) => $page->where('invitation.can_respond', true)->where('invitation.can_decline', true));
    }

    public function test_only_owner_can_invite_and_busy_is_not_a_permission_gate(): void
    {
        $owner = $this->member();
        $recipient = $this->member();
        $project = $this->project($owner);
        $recipient->profile->forceFill(['availability' => 'busy'])->save();
        $this->actingAs($this->member())->post('/freelancers/'.$recipient->profile->id.'/invite', ['project_id' => $project->id])->assertNotFound();
        $this->invite($owner, $recipient, $project);
        $this->post('/freelancers/'.$recipient->profile->id.'/invite', ['project_id' => $project->id])->assertConflict();
        $this->post('/freelancers/'.$owner->profile->id.'/invite', ['project_id' => $project->id])->assertUnprocessable();
        $this->assertDatabaseCount('invitations', 1);
    }

    public function test_draft_and_failed_submit_leave_invitation_pending_and_submit_accepts_once(): void
    {
        $owner = $this->member();
        $recipient = $this->member();
        $project = $this->project($owner);
        $invitation = $this->invite($owner, $recipient, $project);
        $this->actingAs($recipient)->get('/jobs/'.$project->id.'/apply')->assertOk();
        $this->putJson('/jobs/'.$project->id.'/proposal', $this->proposal(0, 'save'))->assertOk();
        $this->assertSame('pending', $invitation->fresh()->status);
        $this->putJson('/jobs/'.$project->id.'/proposal', array_merge($this->proposal(1), ['message' => 'short']))->assertUnprocessable();
        $this->assertSame('pending', $invitation->fresh()->status);
        $this->putJson('/jobs/'.$project->id.'/proposal', $this->proposal(1))->assertOk();
        $this->assertSame('accepted', $invitation->fresh()->status);
        $this->putJson('/jobs/'.$project->id.'/proposal', $this->proposal(2))->assertOk();
        $this->assertSame(1, DB::table('invitation_events')->where('kind', 'accepted')->count());
        $this->actingAs($owner)->post('/freelancers/'.$recipient->profile->id.'/invite', ['project_id' => $project->id])->assertUnprocessable();
    }

    public function test_decline_is_recipient_only_and_resend_observes_exact_cooldown_and_versions(): void
    {
        $this->freezeTime();
        $owner = $this->member();
        $recipient = $this->member();
        $project = $this->project($owner);
        $invitation = $this->invite($owner, $recipient, $project);
        $this->patch('/invitations/'.$invitation->id, ['action' => 'decline', 'version' => 1])->assertNotFound();
        $this->actingAs($recipient)->patch('/invitations/'.$invitation->id, ['action' => 'decline', 'version' => 1])->assertRedirect();
        $this->actingAs($owner)->patch('/invitations/'.$invitation->id, ['action' => 'resend', 'version' => 2])->assertConflict();
        $this->travel(168 * 3600 - 1)->seconds();
        $this->patch('/invitations/'.$invitation->id, ['action' => 'resend', 'version' => 2])->assertConflict();
        $this->travel(1)->seconds();
        $this->patch('/invitations/'.$invitation->id, ['action' => 'resend', 'version' => 2])->assertRedirect();
        $this->assertSame('pending', $invitation->fresh()->status);
        $this->assertSame(3, $invitation->fresh()->version);
        $this->patch('/invitations/'.$invitation->id, ['action' => 'resend', 'version' => 2])->assertConflict();
        $this->assertSame(['sent', 'declined', 'resent'], DB::table('invitation_events')->orderBy('id')->pluck('kind')->all());
    }

    public function test_cutoff_private_profile_and_suspension_prevent_invites(): void
    {
        $owner = $this->member();
        $recipient = $this->member(false);
        $project = $this->project($owner);
        $url = '/freelancers/'.$recipient->profile->id.'/invite';
        $this->actingAs($owner)->post($url, ['project_id' => $project->id])->assertUnprocessable();
        $recipient->profile->forceFill(['published_at' => now()])->save();
        $recipient->forceFill(['status' => 'suspended'])->save();
        $this->post($url, ['project_id' => $project->id])->assertUnprocessable();
        $recipient->forceFill(['status' => 'active'])->save();
        $project->forceFill(['application_closes_at' => now()])->save();
        $this->post($url, ['project_id' => $project->id])->assertUnprocessable();
        $owner->forceFill(['status' => 'suspended'])->save();
        $this->actingAs($owner)->post($url, ['project_id' => $project->id])->assertForbidden();
        $this->assertDatabaseCount('invitations', 0);
    }

    public function test_blocking_cancels_pending_invites_and_unblock_does_not_revive_them(): void
    {
        $owner = $this->member();
        $recipient = $this->member();
        $project = $this->project($owner);
        $invitation = $this->invite($owner, $recipient, $project);
        $this->actingAs($recipient)->post('/freelancers/'.$owner->profile->id.'/block')->assertRedirect();
        $this->assertSame('cancelled', $invitation->fresh()->status);
        $this->putJson('/jobs/'.$project->id.'/proposal', $this->proposal())->assertForbidden();
        $this->actingAs($owner)->post('/freelancers/'.$recipient->profile->id.'/invite', ['project_id' => $project->id])->assertUnprocessable();
        $block = DB::table('user_blocks')->first();
        $this->delete('/blocked-accounts/'.$block->id)->assertNotFound();
        $this->actingAs($recipient)->delete('/blocked-accounts/'.$block->id)->assertRedirect();
        $this->assertSame('cancelled', $invitation->fresh()->status);
        $this->assertDatabaseCount('user_blocks', 0);
    }

    public function test_cooldown_does_not_bypass_new_ineligibility(): void
    {
        $owner = $this->member();
        $recipient = $this->member();
        $project = $this->project($owner);
        $invitation = $this->invite($owner, $recipient, $project);
        $this->actingAs($recipient)->patch('/invitations/'.$invitation->id, ['action' => 'decline', 'version' => 1])->assertRedirect();
        $this->travel(7)->days();
        $recipient->profile->forceFill(['published_at' => null])->save();
        $this->actingAs($owner)->patch('/invitations/'.$invitation->id, ['action' => 'resend', 'version' => 2])->assertUnprocessable();
        $this->assertSame('declined', $invitation->fresh()->status);
        $this->assertDatabaseCount('invitation_events', 2);
    }

    public function test_recipient_can_block_private_sender_through_invitation_without_cross_account_access(): void
    {
        $owner = $this->member(false);
        $recipient = $this->member();
        $project = $this->project($owner);
        $invitation = $this->invite($owner, $recipient, $project);
        $this->actingAs($this->member())->post('/invitations/'.$invitation->id.'/block')->assertNotFound();
        $this->actingAs($recipient)->post('/invitations/'.$invitation->id.'/block')->assertRedirect();
        $this->assertSame('cancelled', $invitation->fresh()->status);
        $this->get('/blocked-accounts')->assertInertia(fn (Assert $page) => $page->has('blocks.data', 1)->missing('blocks.data.0.email')->missing('blocks.data.0.blocked_user_id'));
    }

    public function test_invitation_acceptance_failure_rolls_back_proposal_submission(): void
    {
        $owner = $this->member();
        $recipient = $this->member();
        $project = $this->project($owner);
        $invitation = $this->invite($owner, $recipient, $project);
        DB::listen(static function (QueryExecuted $query): void {
            if (str_contains($query->sql, 'invitation_events') && in_array('accepted', $query->bindings, true)) {
                throw new \RuntimeException('Synthetic invitation event failure');
            }
        });
        $this->actingAs($recipient)->putJson('/jobs/'.$project->id.'/proposal', $this->proposal())->assertServerError();
        $this->assertSame('pending', $invitation->fresh()->status);
        $this->assertDatabaseCount('proposals', 0);
        $this->assertDatabaseCount('invitation_events', 1);
    }
}

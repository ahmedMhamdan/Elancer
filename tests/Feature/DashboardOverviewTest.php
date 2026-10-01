<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Models\Category;
use App\Models\Contract;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Invitation;
use App\Models\Offer;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardOverviewTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, User, Project, Proposal} */
    private function pair(?User $freelancer = null): array
    {
        $client = User::factory()->create(['workspace_role' => WorkspaceRole::Client, 'onboarding_completed_at' => now()]);
        $freelancer ??= User::factory()->create(['workspace_role' => WorkspaceRole::Freelancer, 'onboarding_completed_at' => now()]);
        $project = new Project;
        $project->forceFill(['user_id' => $client->id, 'category_id' => Category::create(['categoryname' => 'Category '.Str::uuid()])->id,
            'title' => 'Private project '.$client->id, 'description' => 'A complete project brief.', 'budget_min' => 100, 'budget_max' => 500,
            'status' => 'published', 'published_at' => now()->subDay(), 'application_closes_at' => now()->addDay()])->save();
        $proposal = new Proposal;
        $proposal->forceFill(['project_id' => $project->id, 'user_id' => $freelancer->id, 'status' => 'submitted',
            'submitted_at' => now(), 'content' => ['price' => '300.00', 'duration_days' => 7]])->save();

        return [$client, $freelancer, $project, $proposal];
    }

    public function test_new_account_has_honest_zero_counts_and_no_profile_creation(): void
    {
        $user = User::factory()->create(['workspace_role' => WorkspaceRole::Freelancer, 'onboarding_completed_at' => now()]);
        $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('overview.client', false)->where('overview.counts.projects', 0)->where('overview.counts.proposals', 0)
            ->where('overview.counts.contracts', 0)->where('overview.counts.unread', 0)->has('overview.chart', 8)
            ->has('overview.work', 0)->has('overview.activity', 0)->where('overview.profile_checks.skills', false));
        $this->assertDatabaseCount('profiles', 0);
        $this->get('/my-profile')->assertInertia(fn (Assert $page) => $page->missing('overview'));
    }

    public function test_overview_preserves_participant_privacy_and_private_unread_state(): void
    {
        [$client, $freelancer, $project, $proposal] = $this->pair();
        [$foreignClient, $foreignFreelancer, $foreignProject, $foreignProposal] = $this->pair();
        $draft = new Proposal;
        $draft->forceFill(['project_id' => $project->id, 'user_id' => $foreignFreelancer->id, 'status' => 'draft', 'draft' => ['body' => 'Hidden application draft']])->save();
        $trash = new Project;
        $trash->forceFill(['user_id' => $client->id, 'title' => 'Deleted private draft'])->save();
        $trash->delete();

        $this->actingAs($client)->post('/proposals/'.$proposal->id.'/offer', [
            'scope' => 'Implement the agreed application and setup documentation.',
            'deliverables' => ['Application source'], 'amount' => '300.00', 'duration_days' => 7,
            'revision_rounds' => 1, 'proposal_version' => 1, 'client_token' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $offer = Offer::query()->where('proposal_id', $proposal->id)->firstOrFail();
        $this->actingAs($freelancer)->patch('/offers/'.$offer->id, ['action' => 'accept', 'version' => 1])->assertSessionHasNoErrors()->assertRedirect();
        $contract = Contract::query()->where('proposal_id', $proposal->id)->firstOrFail();
        $conversation = Conversation::findOrFail($contract->conversation_id);
        $first = new ConversationMessage;
        $first->forceFill(['conversation_id' => $conversation->id, 'sender_id' => $freelancer->id, 'body' => 'Already read.', 'client_token' => (string) Str::uuid()])->save();
        $second = new ConversationMessage;
        $second->forceFill(['conversation_id' => $conversation->id, 'sender_id' => $freelancer->id, 'body' => 'Visible participant message.', 'client_token' => (string) Str::uuid()])->save();
        $conversation->forceFill(['client_read_through' => $first->id, 'client_archived' => false])->save();
        $foreignThread = new Conversation;
        $foreignThread->forceFill(['proposal_id' => $foreignProposal->id, 'client_id' => $foreignClient->id, 'freelancer_id' => $foreignFreelancer->id])->save();
        $secret = new ConversationMessage;
        $secret->forceFill(['conversation_id' => $foreignThread->id, 'sender_id' => $foreignFreelancer->id, 'body' => 'Foreign message secret.', 'client_token' => (string) Str::uuid()])->save();

        $response = $this->actingAs($client)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('overview.counts.projects', 1)->where('overview.counts.proposals', 1)->where('overview.counts.contracts', 1)
            ->where('overview.counts.awaiting_payment', 1)->where('overview.counts.unread', 1)->has('overview.work', 1)
            ->where('overview.work.0.count', 1)->where('overview.agreements.0.href', '/contracts/'.$contract->id)
            ->missing('overview.activity.0.client_token')->missing('overview.activity.0.freelancer_read_through'));
        $response->assertDontSee('Foreign message secret.')->assertDontSee('Hidden application draft')->assertDontSee('Deleted private draft');

        $conversation->forceFill(['client_archived' => true])->save();
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('overview.counts.unread', 0));

        $outsider = User::factory()->create(['is_admin' => true, 'onboarding_completed_at' => now()]);
        $this->actingAs($outsider)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('overview.counts.contracts', 0)->where('overview.counts.unread', 0)->has('overview.activity', 0));
        $this->actingAs($freelancer)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('overview.counts.proposals', 1)->where('overview.counts.contracts', 1)->where('overview.counts.unread', 0));
    }

    public function test_workspace_switch_changes_presentation_without_widening_access(): void
    {
        [$client, $freelancer, $project] = $this->pair();
        $own = new Project;
        $own->forceFill(['user_id' => $freelancer->id, 'title' => 'Owned draft'])->save();
        $invitation = new Invitation;
        $invitation->forceFill(['project_id' => $project->id, 'recipient_id' => $freelancer->id, 'status' => 'pending', 'sent_at' => now()])->save();
        $this->actingAs($freelancer)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('overview.client', false)->where('overview.counts.proposals', 1)->where('overview.counts.invitations', 1)
            ->where('overview.work.0.kind', 'proposal'));
        $freelancer->forceFill(['workspace_role' => WorkspaceRole::Client])->save();
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('overview.client', true)->where('overview.counts.projects', 1)->where('overview.counts.proposals', 0)
            ->where('overview.work.0.title', 'Owned draft'));
        $this->get('/my-projects/'.$project->id.'/edit')->assertNotFound();
    }

    public function test_chart_uses_exact_calendar_boundaries_and_excludes_future_submissions(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-30 12:00:00', 'UTC'));
        [$client, $freelancer, $project, $proposal] = $this->pair();
        $start = CarbonImmutable::now()->startOfWeek()->subWeeks(7);
        $proposal->forceFill(['submitted_at' => $start])->save();
        foreach ([$start->subSecond(), now(), now()->addSecond()] as $date) {
            [, , , $application] = $this->pair($freelancer);
            $application->forceFill(['submitted_at' => $date])->save();
        }
        $this->actingAs($freelancer)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->has('overview.chart', 8)->where('overview.chart.0.week', $start->toDateString())
            ->where('overview.chart.0.proposals', 1)->where('overview.chart.7.proposals', 1)
            ->where('overview.chart.1.proposals', 0)->where('overview.chart.7.contracts', 0)
            ->where('overview.counts.proposals', 4));
    }
}

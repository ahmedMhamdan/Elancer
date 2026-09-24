<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Models\Category;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FreelancerProposalTest extends TestCase
{
    use RefreshDatabase;

    private function member(bool $published = true): User
    {
        $user = User::factory()->create(['workspace_role' => WorkspaceRole::Freelancer, 'onboarding_completed_at' => now()]);
        $profile = $user->profile()->create(['headline' => 'Laravel developer', 'bio' => 'I build accessible applications.']);
        $profile->syncSkillTags(['Laravel']);
        $profile->forceFill(['published_at' => $published ? now() : null])->save();

        return $user;
    }

    private function project(?User $owner = null, array $attributes = []): Project
    {
        $project = new Project;
        $project->forceFill(array_merge([
            'user_id' => ($owner ?? $this->member())->id,
            'category_id' => Category::create(['categoryname' => 'Development'])->id,
            'title' => 'Build a website', 'description' => str_repeat('A clear specification for the project. ', 3),
            'budget_min' => 100, 'budget_max' => 500, 'status' => 'published',
            'published_at' => now()->subHour(), 'application_closes_at' => now()->addDays(7),
            'screening_questions' => ['How will you approach this project?'],
        ], $attributes))->save();
        $project->skills()->sync(Skill::query()->where('name', 'Laravel')->pluck('id'));

        return $project;
    }

    private function payload(array $attributes = []): array
    {
        return array_merge(['action' => 'submit', 'version' => 0, 'price' => '650.50', 'duration_days' => 14, 'message' => 'I will build and test your accessible Laravel application.', 'answers' => ['Start with a clear plan and deliver in milestones.'], 'samples' => [['label' => 'Demo', 'url' => 'https://example.com/demo']]], $attributes);
    }

    private function submit(User $user, Project $project): Proposal
    {
        $this->actingAs($user)->putJson('/jobs/'.$project->id.'/proposal', $this->payload())->assertOk();

        return Proposal::query()->where('project_id', $project->id)->where('user_id', $user->id)->firstOrFail();
    }

    public function test_publication_requires_complete_profile_and_can_be_reversed(): void
    {
        $user = $this->member(false);
        $user->profile->forceFill(['bio' => null])->save();
        $this->actingAs($user)->patch('/my-profile/publication', ['published' => true])->assertSessionHasErrors('publication');
        $this->patch('/my-profile', ['bio' => 'A useful biography.'])->assertSessionHasNoErrors();
        $this->patch('/my-profile/publication', ['published' => true])->assertSessionHasNoErrors();
        $this->get('/freelancers/'.$user->profile->id)->assertOk()->assertInertia(fn (Assert $page) => $page->missing('freelancer.email')->missing('freelancer.photo_path')->missing('freelancer.user_id')->where('freelancer.headline', 'Laravel developer'));
        $this->patch('/my-profile/publication', ['published' => false])->assertRedirect();
        $this->get('/freelancers/'.$user->profile->id)->assertNotFound();
    }

    public function test_directory_combines_filters_and_hides_ineligible_and_private_profiles(): void
    {
        $match = $this->member();
        $match->profile->forceFill(['headline' => '100%_ready Laravel'])->save();
        $this->member(false);
        $this->member()->forceFill(['status' => 'suspended'])->save();
        $this->member()->forceFill(['email_verified_at' => null])->save();
        $this->member()->forceFill(['onboarding_completed_at' => null])->save();
        $this->member()->profile->forceFill(['bio' => ''])->save();
        $this->member()->profile->forceFill(['published_at' => now()->addDay()])->save();
        $this->get('/freelancers')->assertInertia(fn (Assert $page) => $page->has('freelancers.data', 1));
        $query = http_build_query(['q' => '100%_READY', 'skill' => Skill::where('name', 'Laravel')->firstOrFail()->id, 'availability' => 'available']);
        $this->get('/freelancers?'.$query)->assertInertia(fn (Assert $page) => $page->where('freelancers.data.0.id', $match->profile->id));
        $this->get('/freelancers?availability=busy')->assertInertia(fn (Assert $page) => $page->has('freelancers.data', 0));
        $this->get('/freelancers?page[]=1')->assertUnprocessable();
    }

    public function test_photo_visibility_and_links_validation_follow_publication(): void
    {
        Storage::fake('local');
        $user = $this->member();
        Storage::disk('local')->put('profile-photos/example.jpg', 'synthetic');
        $user->profile->forceFill(['photo_path' => 'profile-photos/example.jpg'])->save();
        $this->get('/freelancers/'.$user->profile->id.'/photo')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($user)->patch('/my-profile', ['professional_links' => [['label' => 'Bad link', 'url' => 'javascript:alert(1)']]])->assertSessionHasErrors('professional_links.0.url');
        $this->patch('/my-profile', ['availability' => 'busy', 'professional_links' => [['label' => 'Portfolio', 'url' => 'https://example.com']]])->assertSessionHasNoErrors();
        $this->assertSame('busy', $user->profile->fresh()->availability);
        $this->patch('/my-profile', ['skills' => []])->assertSessionHasNoErrors();
        $this->assertNull($user->profile->fresh()->published_at);
        $this->get('/freelancers/'.$user->profile->id.'/photo')->assertNotFound();
    }

    public function test_draft_is_private_and_stale_save_cannot_overwrite_it(): void
    {
        $owner = $this->member();
        $project = $this->project($owner);
        $author = $this->member(false);
        $this->actingAs($author)->putJson('/jobs/'.$project->id.'/proposal', $this->payload(['action' => 'save', 'price' => null, 'duration_days' => null, 'message' => 'Draft']))->assertOk()->assertJsonPath('proposal.version', 1);
        $proposal = Proposal::firstOrFail();
        $this->putJson('/jobs/'.$project->id.'/proposal', $this->payload(['action' => 'save', 'message' => 'Stale']))->assertStatus(409)->assertJsonPath('conflict', true);
        $this->assertSame('Draft', $proposal->fresh()->draft['message']);
        $this->assertNull($proposal->submitted_at);
        $this->actingAs($owner)->get('/proposals/'.$proposal->id)->assertNotFound();
        $this->get('/my-projects/'.$project->id.'/proposals')->assertInertia(fn (Assert $page) => $page->has('proposals.data', 0));
        $this->get('/jobs/'.$project->id)->assertInertia(fn (Assert $page) => $page->where('project.proposals_received', 0));
        $this->actingAs($this->member())->get('/proposals/'.$proposal->id)->assertNotFound();
    }

    public function test_submit_requires_public_profile_answers_and_safe_links(): void
    {
        $project = $this->project();
        $user = $this->member(false);
        $this->actingAs($user)->putJson('/jobs/'.$project->id.'/proposal', $this->payload())->assertUnprocessable();
        $this->patch('/my-profile/publication', ['published' => true])->assertSessionHasNoErrors();
        $this->putJson('/jobs/'.$project->id.'/proposal', $this->payload(['answers' => []]))->assertJsonValidationErrors('answers');
        $this->putJson('/jobs/'.$project->id.'/proposal', $this->payload(['samples' => [['label' => 'Bad', 'url' => 'http://example.com']]]))->assertJsonValidationErrors('samples.0.url');
        $this->putJson('/jobs/'.$project->id.'/proposal', $this->payload(['user_id' => $project->user_id, 'client_note' => 'forged', 'status' => 'submitted']))->assertJsonValidationErrors(['user_id', 'client_note', 'status']);
        $this->assertDatabaseCount('proposals', 0);
    }

    public function test_submit_once_captures_snapshot_and_counts_unique_receipts(): void
    {
        $project = $this->project();
        $user = $this->member();
        $proposal = $this->submit($user, $project);
        $this->assertSame('650.50', $proposal->content['price']);
        $this->assertSame($user->name, $proposal->profile_snapshot['name']);
        $this->putJson('/jobs/'.$project->id.'/proposal', $this->payload())->assertStatus(409);
        $this->assertDatabaseCount('proposals', 1);
        $this->assertDatabaseCount('proposal_events', 1);
        $this->get('/jobs/'.$project->id)->assertInertia(fn (Assert $page) => $page->where('project.proposals_received', 1));
        $this->get('/my-proposals')->assertInertia(fn (Assert $page) => $page->has('proposals.data', 1));
    }

    public function test_no_self_bid_and_closed_or_ineligible_accounts_cannot_apply(): void
    {
        $owner = $this->member();
        $project = $this->project($owner);
        $this->actingAs($owner)->putJson('/jobs/'.$project->id.'/proposal', $this->payload())->assertForbidden();
        $author = $this->member();
        $project->forceFill(['application_closes_at' => now()->subSecond()])->save();
        $this->actingAs($author)->putJson('/jobs/'.$project->id.'/proposal', $this->payload())->assertStatus(409);
        $project->forceFill(['application_closes_at' => now()->addDay(), 'status' => 'draft'])->save();
        $this->putJson('/jobs/'.$project->id.'/proposal', $this->payload())->assertStatus(409);
        $project->forceFill(['status' => 'published'])->save();
        $author->forceFill(['status' => 'suspended'])->save();
        $this->actingAs($author)->putJson('/jobs/'.$project->id.'/proposal', $this->payload())->assertForbidden();
        $author->forceFill(['status' => 'active', 'email_verified_at' => null])->save();
        $this->actingAs($author)->putJson('/jobs/'.$project->id.'/proposal', $this->payload())->assertForbidden();
    }

    public function test_draft_revisions_and_private_profile_edits_do_not_leak_to_client(): void
    {
        $owner = $this->member();
        $project = $this->project($owner);
        $user = $this->member();
        $proposal = $this->submit($user, $project);
        $user->profile->forceFill(['headline' => 'PRIVATE NEW HEADLINE', 'published_at' => null])->save();
        $project->forceFill(['application_closes_at' => now()->subHour()])->save();
        $this->putJson('/jobs/'.$project->id.'/proposal', $this->payload(['action' => 'save', 'version' => 1, 'message' => 'PRIVATE REVISION']))->assertOk();
        $this->actingAs($owner)->get('/proposals/'.$proposal->id)->assertInertia(fn (Assert $page) => $page->missing('proposal.draft')->where('proposal.profile_snapshot.headline', 'Laravel developer')->where('proposal.content.message', $this->payload()['message']));
        $this->actingAs($user)->putJson('/jobs/'.$project->id.'/proposal', $this->payload(['version' => 2, 'message' => 'A revised and deliberately submitted message.']))->assertOk();
        $this->assertDatabaseCount('proposal_events', 2);
        $this->actingAs($owner)->get('/proposals/'.$proposal->id)->assertInertia(fn (Assert $page) => $page->where('proposal.content.message', 'A revised and deliberately submitted message.')->where('proposal.events.1.content.message', $this->payload()['message']));
    }

    public function test_withdraw_and_resubmit_preserve_one_record_and_reject_stale_versions(): void
    {
        $project = $this->project();
        $user = $this->member();
        $proposal = $this->submit($user, $project);
        $this->post('/proposals/'.$proposal->id.'/withdraw', ['version' => 99])->assertStatus(409);
        $this->post('/proposals/'.$proposal->id.'/withdraw', ['version' => 1])->assertRedirect();
        $this->assertSame('withdrawn', $proposal->fresh()->status);
        $this->putJson('/jobs/'.$project->id.'/proposal', $this->payload(['version' => 2]))->assertOk();
        $this->assertDatabaseCount('proposals', 1);
        $this->assertDatabaseCount('proposal_events', 3);
        $this->post('/proposals/'.$proposal->id.'/withdraw', ['version' => 3])->assertRedirect();
        $project->forceFill(['application_closes_at' => now()->subHour()])->save();
        $this->putJson('/jobs/'.$project->id.'/proposal', $this->payload(['version' => 4]))->assertStatus(409);
        $this->get('/jobs/'.$project->id)->assertInertia(fn (Assert $page) => $page->where('project.proposals_received', 1));
    }

    public function test_client_notes_are_private_and_decline_reopen_work_after_cutoff(): void
    {
        $owner = $this->member();
        $project = $this->project($owner);
        $author = $this->member();
        $proposal = $this->submit($author, $project);
        $project->forceFill(['application_closes_at' => now()->subHour()])->save();
        $review = ['action' => 'decline', 'organization' => 'shortlisted', 'client_note' => 'PRIVATE CLIENT NOTE', 'version' => 1];
        $this->actingAs($owner)->patch('/proposals/'.$proposal->id.'/review', $review)->assertRedirect();
        $this->patch('/proposals/'.$proposal->id.'/review', $review)->assertStatus(409);
        $this->actingAs($author)->get('/proposals/'.$proposal->id)->assertInertia(fn (Assert $page) => $page->missing('proposal.client_note')->missing('proposal.organization')->where('proposal.status', 'declined'));
        $this->putJson('/jobs/'.$project->id.'/proposal', $this->payload(['version' => 2]))->assertStatus(409);
        $this->actingAs($owner)->patch('/proposals/'.$proposal->id.'/review', array_merge($review, ['action' => 'reopen', 'version' => 2]))->assertRedirect();
        $this->actingAs($author)->putJson('/jobs/'.$project->id.'/proposal', $this->payload(['version' => 3]))->assertOk();
        $this->assertSame('submitted', $proposal->fresh()->status);
        $this->assertDatabaseCount('proposal_events', 4);
    }

    public function test_review_and_comparison_are_scoped_to_owner_and_submitted_project_records(): void
    {
        $owner = $this->member();
        $project = $this->project($owner);
        $proposal = $this->submit($this->member(), $project);
        $other = $this->submit($this->member(), $this->project());
        $review = ['action' => 'organize', 'organization' => 'archived', 'version' => 1];
        $this->actingAs($this->member())->patch('/proposals/'.$proposal->id.'/review', $review)->assertNotFound();
        $this->get('/my-projects/'.$project->id.'/proposals')->assertNotFound();
        $this->actingAs($owner)->get('/my-projects/'.$project->id.'/proposals/compare?ids[]='.$proposal->id)->assertInertia(fn (Assert $page) => $page->has('proposals', 1)->missing('proposals.0.draft'));
        $this->get('/my-projects/'.$project->id.'/proposals/compare?ids[]='.$other->id)->assertNotFound();
        $this->get('/my-projects/'.$project->id.'/proposals/compare?ids[]=1&ids[]=2&ids[]=3&ids[]=4')->assertSessionHasErrors('ids');
        $this->patch('/proposals/'.$proposal->id.'/review', $review)->assertRedirect();
        $this->get('/my-projects/'.$project->id.'/proposals?organization=archived&sort=price')->assertInertia(fn (Assert $page) => $page->has('proposals.data', 1));
        $project->forceFill(['status' => 'closed'])->save();
        $this->patch('/proposals/'.$proposal->id.'/review', array_merge($review, ['version' => 2]))->assertStatus(409);
    }
}

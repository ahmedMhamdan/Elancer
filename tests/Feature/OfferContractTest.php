<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Contract;
use App\Models\Conversation;
use App\Models\Offer;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OfferContractTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, User, Proposal} */
    private function participants(): array
    {
        $client = User::factory()->create(['onboarding_completed_at' => now()]);
        $freelancer = User::factory()->create(['onboarding_completed_at' => now()]);
        $project = new Project;
        $project->forceFill(['user_id' => $client->id, 'category_id' => Category::create(['categoryname' => 'Development'])->id,
            'title' => 'Build an application', 'description' => 'A real project brief.', 'budget_min' => 100, 'budget_max' => 500,
            'status' => 'published', 'published_at' => now()->subDay(), 'application_closes_at' => now()->subHour()])->save();
        $proposal = new Proposal;
        $proposal->forceFill(['project_id' => $project->id, 'user_id' => $freelancer->id, 'status' => 'submitted', 'version' => 1,
            'content' => ['price' => '300.00', 'duration_days' => 7], 'submitted_at' => now()->subHours(2)])->save();

        return [$client, $freelancer, $proposal];
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return ['scope' => 'Build a bilingual application with the agreed account and project workflows.',
            'deliverables' => ['Application source code', 'Setup documentation'], 'amount' => '750.25', 'duration_days' => 14,
            'revision_rounds' => 2, 'proposal_version' => 1, 'client_token' => (string) Str::uuid()];
    }

    private function offer(User $client, Proposal $proposal): Offer
    {
        $this->actingAs($client)->post('/proposals/'.$proposal->id.'/offer', $this->payload())->assertSessionHasNoErrors()->assertRedirect();

        return Offer::query()->latest('id')->firstOrFail();
    }

    public function test_only_owner_can_send_and_only_participants_can_read_or_respond(): void
    {
        [$client, $freelancer, $proposal] = $this->participants();
        $this->actingAs($freelancer)->post('/proposals/'.$proposal->id.'/offer', $this->payload())->assertNotFound();
        $offer = $this->offer($client, $proposal);
        $this->patch('/offers/'.$offer->id, ['action' => 'accept', 'version' => 1])->assertForbidden();
        $this->actingAs($freelancer)->patch('/offers/'.$offer->id, ['action' => 'withdraw', 'version' => 1])->assertForbidden();
        $other = User::factory()->create(['onboarding_completed_at' => now(), 'is_admin' => true]);
        $this->actingAs($other)->get('/offers/'.$offer->id)->assertNotFound();
        $this->patch('/offers/'.$offer->id, ['action' => 'accept', 'version' => 1])->assertNotFound();
        $this->get('/offers')->assertInertia(fn (Assert $page) => $page->has('offers.data', 0));
        $this->actingAs($freelancer)->get('/offers/'.$offer->id)->assertInertia(fn (Assert $page) => $page->missing('offer.client_token')->missing('offer.client_id'));
    }

    public function test_send_retry_is_idempotent_slot_is_project_wide_and_terms_are_validated(): void
    {
        [$client, $freelancer, $proposal] = $this->participants();
        $payload = $this->payload();
        $payload['scope'] = '0e'.str_repeat('1', 50);
        $url = '/proposals/'.$proposal->id.'/offer';
        $this->actingAs($client)->post($url, [...$payload, 'amount' => '10.123', 'deliverables' => []])->assertSessionHasErrors(['amount', 'deliverables']);
        $this->post($url, $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->post($url, $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->post($url, [...$payload, 'amount' => '900'])->assertSessionHasErrors('offer');
        $this->post($url, [...$payload, 'scope' => '0e'.str_repeat('2', 50)])->assertSessionHasErrors('offer');
        $second = $proposal->replicate();
        $second->forceFill(['user_id' => User::factory()->create(['onboarding_completed_at' => now()])->id])->save();
        $this->post('/proposals/'.$second->id.'/offer', $this->payload())->assertSessionHasErrors('offer');
        $this->assertDatabaseCount('offers', 1);
        $this->actingAs($freelancer)->get('/jobs/'.$proposal->project_id.'/apply')->assertConflict();
        $this->post('/proposals/'.$proposal->id.'/withdraw', ['version' => 1])->assertConflict();
        $this->actingAs($client)->patch('/proposals/'.$proposal->id.'/review', ['action' => 'decline', 'organization' => 'received', 'version' => 1])->assertConflict();
    }

    public function test_expiry_boundary_is_enforced_without_scheduler_and_frees_slot(): void
    {
        $this->freezeSecond();
        [$client, $freelancer, $proposal] = $this->participants();
        $offer = $this->offer($client, $proposal);
        $this->assertTrue($offer->expires_at->equalTo(now()->addHours(72)));
        $this->travel(72)->hours();
        $this->actingAs($freelancer)->patch('/offers/'.$offer->id, ['action' => 'accept', 'version' => 1])->assertSessionHasErrors('offer');
        $this->assertSame('expired', $offer->fresh()->status);
        $this->assertDatabaseCount('contracts', 0);
        $this->get('/jobs/'.$proposal->project_id.'/apply')->assertOk();
        $replacement = $this->offer($client, $proposal);
        $this->assertTrue($replacement->expires_at->equalTo(now()->addHours(72)));
        $this->assertDatabaseCount('offers', 2);
    }

    public function test_changes_withdrawal_and_decline_preserve_history_and_reject_stale_acceptance(): void
    {
        [$client, $freelancer, $proposal] = $this->participants();
        $offer = $this->offer($client, $proposal);
        $this->actingAs($freelancer)->patch('/offers/'.$offer->id, ['action' => 'changes_requested', 'version' => 1])->assertSessionHasErrors('reason');
        $response = ['action' => 'changes_requested', 'version' => 1, 'reason' => 'Please include deployment documentation.'];
        $this->patch('/offers/'.$offer->id, $response)->assertSessionHasNoErrors();
        $this->patch('/offers/'.$offer->id, $response)->assertSessionHasNoErrors();
        $this->assertSame($response['reason'], $offer->fresh()->reason);
        $replacement = $this->offer($client, $proposal);
        $this->actingAs($freelancer)->patch('/offers/'.$offer->id, ['action' => 'accept', 'version' => 1])->assertSessionHasErrors('offer');
        $this->actingAs($client)->patch('/offers/'.$replacement->id, ['action' => 'withdraw', 'version' => 1])->assertSessionHasNoErrors();
        $last = $this->offer($client, $proposal);
        $this->actingAs($freelancer)->patch('/offers/'.$last->id, ['action' => 'decline', 'version' => 1])->assertSessionHasNoErrors();
        $this->assertSame('submitted', $proposal->fresh()->status);
        $this->get('/offers/'.$last->id)->assertInertia(fn (Assert $page) => $page->has('history', 3)->where('history.2.reason', $response['reason']));
    }

    public function test_acceptance_closes_hiring_once_and_preserves_private_immutable_agreement(): void
    {
        [$client, $freelancer, $proposal] = $this->participants();
        $offer = $this->offer($client, $proposal);
        $payload = ['action' => 'accept', 'version' => 1];
        $this->actingAs($freelancer)->patch('/offers/'.$offer->id, $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->patch('/offers/'.$offer->id, $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('contracts', 1);
        $contract = Contract::query()->firstOrFail();
        $this->assertSame('awaiting_payment', $contract->status);
        $this->assertSame('750.25', $contract->agreement['amount']);
        $this->assertSame('hired', $proposal->project->fresh()->status);
        $this->get('/jobs/'.$proposal->project_id.'/apply')->assertConflict();
        $this->get('/jobs/'.$proposal->project_id)->assertInertia(fn (Assert $page) => $page->where('project.open', false));
        $this->get('/messages?kind=contracts')->assertInertia(fn (Assert $page) => $page->has('conversations.data', 1));
        $this->get('/messages?kind=hiring')->assertInertia(fn (Assert $page) => $page->has('conversations.data', 0));
        $other = User::factory()->create(['onboarding_completed_at' => now()]);
        $this->actingAs($other)->get('/contracts/'.$contract->id)->assertNotFound();
        $this->get('/contracts')->assertInertia(fn (Assert $page) => $page->has('contracts.data', 0));
        $this->expectException(\LogicException::class);
        $contract->forceFill(['agreement' => ['amount' => '1.00']])->save();
    }

    public function test_block_and_suspension_close_offers_without_revival_but_preserve_contract_communication(): void
    {
        [$client, $freelancer, $proposal] = $this->participants();
        $this->actingAs($client)->post('/proposals/'.$proposal->id.'/conversation', ['body' => 'Discuss scope', 'client_token' => (string) Str::uuid()]);
        $conversation = Conversation::query()->firstOrFail();
        $offer = $this->offer($client, $proposal);
        $this->actingAs($freelancer)->post('/messages/'.$conversation->id.'/block')->assertRedirect();
        $this->assertSame('restricted', $offer->fresh()->status);
        $this->delete('/blocked-accounts/'.DB::table('user_blocks')->value('id'))->assertRedirect();
        $this->patch('/offers/'.$offer->id, ['action' => 'accept', 'version' => 1])->assertSessionHasErrors('offer');
        $second = $this->offer($client, $proposal);
        $freelancer->forceFill(['status' => 'suspended'])->save();
        $this->assertSame('restricted', $second->fresh()->status);
        $freelancer->forceFill(['status' => 'active'])->save();
        $this->actingAs($freelancer)->patch('/offers/'.$second->id, ['action' => 'accept', 'version' => 1])->assertSessionHasErrors('offer');
        $third = $this->offer($client, $proposal);
        $this->actingAs($freelancer)->patch('/offers/'.$third->id, ['action' => 'accept', 'version' => 1])->assertSessionHasNoErrors();
        $freelancer->forceFill(['status' => 'suspended'])->save();
        $this->post('/messages/'.$conversation->id.'/block')->assertRedirect();
        $this->post('/messages/'.$conversation->id, ['body' => 'Existing obligations remain.', 'client_token' => (string) Str::uuid()])->assertRedirect();
        $this->get('/contracts/'.Contract::query()->value('id'))->assertOk();
        $this->assertSame('accepted', $third->fresh()->status);
    }

    public function test_acceptance_closes_other_applicants_threads_and_prevents_another_offer(): void
    {
        [$client, $freelancer, $proposal] = $this->participants();
        $other = User::factory()->create(['onboarding_completed_at' => now()]);
        $competing = $proposal->replicate();
        $competing->forceFill(['user_id' => $other->id])->save();
        $this->actingAs($client)->post('/proposals/'.$competing->id.'/conversation', ['body' => 'Existing discussion', 'client_token' => (string) Str::uuid()])->assertRedirect();
        $conversation = Conversation::query()->where('proposal_id', $competing->id)->firstOrFail();
        $offer = $this->offer($client, $proposal);
        $this->actingAs($freelancer)->patch('/offers/'.$offer->id, ['action' => 'accept', 'version' => 1])->assertSessionHasNoErrors();
        $this->actingAs($other)->get('/messages/'.$conversation->id)->assertInertia(fn (Assert $page) => $page->where('writable', false));
        $this->post('/messages/'.$conversation->id, ['body' => 'Stale reply', 'client_token' => (string) Str::uuid()])->assertConflict();
        $this->actingAs($client)->post('/proposals/'.$competing->id.'/offer', $this->payload())->assertSessionHasErrors('offer');
        $this->assertDatabaseCount('contracts', 1);
    }

    public function test_database_rejects_a_second_pending_slot_even_without_the_controller(): void
    {
        [$client, , $proposal] = $this->participants();
        $offer = $this->offer($client, $proposal);
        $duplicate = $offer->replicate();
        $duplicate->forceFill(['client_token' => (string) Str::uuid()]);
        $this->expectException(QueryException::class);
        $duplicate->save();
    }

    public function test_database_rejects_a_second_contract_for_the_same_project(): void
    {
        [$client, $freelancer, $proposal] = $this->participants();
        $offer = $this->offer($client, $proposal);
        $this->actingAs($freelancer)->patch('/offers/'.$offer->id, ['action' => 'accept', 'version' => 1])->assertSessionHasNoErrors();
        $duplicate = Contract::query()->firstOrFail()->replicate();
        $this->expectException(QueryException::class);
        $duplicate->save();
    }

    public function test_account_deletion_cannot_erase_contract_or_log_out_the_rejected_user(): void
    {
        [$client, $freelancer, $proposal] = $this->participants();
        $offer = $this->offer($client, $proposal);
        $this->actingAs($freelancer)->patch('/offers/'.$offer->id, ['action' => 'accept', 'version' => 1])->assertSessionHasNoErrors();
        $this->delete('/settings/profile', ['password' => 'password'])->assertSessionHasErrors('password');
        $this->assertAuthenticatedAs($freelancer);
        $this->assertDatabaseHas('users', ['id' => $freelancer->id]);
        $this->assertDatabaseCount('contracts', 1);
        $this->actingAs($client)->delete('/settings/profile', ['password' => 'password'])->assertSessionHasErrors('password');
        $this->assertAuthenticatedAs($client);
    }
}

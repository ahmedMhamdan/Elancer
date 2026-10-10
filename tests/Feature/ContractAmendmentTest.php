<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Contract;
use App\Models\ContractAmendment;
use App\Models\ContractSubmission;
use App\Models\Offer;
use App\Models\PaymentAttempt;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContractAmendmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('uploads');
        // Real keys in a developer's .env must never reach the tests.
        config(['payments.simulator.enabled' => true, 'payments.stripe.secret' => null, 'payments.stripe.webhook_secret' => null,
            'payments.moyasar.secret' => null]);
    }

    /** @return array{User, User, Contract} */
    private function contract(int $rounds = 1): array
    {
        $client = User::factory()->create(['onboarding_completed_at' => now()]);
        $freelancer = User::factory()->create(['onboarding_completed_at' => now()]);
        $project = new Project;
        $project->forceFill(['user_id' => $client->id, 'category_id' => Category::create(['categoryname' => 'Development '.Str::uuid()])->id,
            'title' => 'Build an application', 'description' => 'A real project brief.', 'budget_min' => 100, 'budget_max' => 500,
            'status' => 'published', 'published_at' => now()->subDay(), 'application_closes_at' => now()->subHour()])->save();
        $proposal = new Proposal;
        $proposal->forceFill(['project_id' => $project->id, 'user_id' => $freelancer->id, 'status' => 'submitted', 'version' => 1,
            'content' => ['price' => '300.00', 'duration_days' => 7], 'submitted_at' => now()->subHours(2)])->save();
        $this->actingAs($client)->post('/proposals/'.$proposal->id.'/offer', ['scope' => 'Build a bilingual application with the agreed account and project workflows.',
            'deliverables' => ['Application source code'], 'amount' => '750.25', 'duration_days' => 14, 'revision_rounds' => $rounds,
            'proposal_version' => 1, 'client_token' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->actingAs($freelancer)->patch('/offers/'.Offer::query()->latest('id')->value('id'), ['action' => 'accept', 'version' => 1])->assertSessionHasNoErrors();
        $contract = Contract::query()->latest('id')->firstOrFail();
        $contract->forceFill(['status' => 'active', 'funded_at' => now(), 'delivery_due_at' => now()->addDays(14)])->save();

        return [$client, $freelancer, $contract];
    }

    private function deliver(User $freelancer, Contract $contract): ContractSubmission
    {
        $this->actingAs($freelancer)->post('/contracts/'.$contract->id.'/deliveries', ['message' => 'The complete application is ready for your review.',
            'complete' => true, 'client_token' => (string) Str::uuid()])->assertSessionHasNoErrors();

        return ContractSubmission::query()->latest('id')->firstOrFail();
    }

    public function test_extra_rounds_bind_only_on_the_counterparts_acceptance_and_keep_the_agreement(): void
    {
        [$client, $freelancer, $contract] = $this->contract(rounds: 0);
        $submission = $this->deliver($freelancer, $contract);
        $revision = ['submission' => $submission->id, 'changes' => 'Please correct the Arabic layout and the totals.'];
        $this->actingAs($client)->post('/contracts/'.$contract->id.'/revisions', $revision)->assertSessionHasErrors('revision');
        $path = '/contracts/'.$contract->id.'/amendments';
        $stranger = User::factory()->create(['onboarding_completed_at' => now(), 'is_admin' => true]);
        $this->actingAs($stranger)->post($path, ['reason' => 'One more round is needed.', 'extra_rounds' => 1])->assertNotFound();
        // Something must change, within bounds; no date applies while a delivery is under review.
        $this->actingAs($client)->post($path, ['reason' => 'One more round is needed.'])->assertSessionHasErrors(['due_at', 'extra_rounds']);
        $this->post($path, ['reason' => 'One more round is needed.', 'extra_rounds' => 6])->assertSessionHasErrors('extra_rounds');
        $this->post($path, ['reason' => 'One more round is needed.', 'due_at' => now()->addDays(3)->toIso8601String()])->assertSessionHasErrors('due_at');
        $this->post($path, ['reason' => 'One more round is needed.', 'extra_rounds' => 1])->assertSessionHasNoErrors();
        $this->post($path, ['reason' => 'A second proposal while one is open.', 'extra_rounds' => 1])->assertSessionHasErrors('amendment');
        $amendment = ContractAmendment::query()->firstOrFail();
        $this->assertSame(1, $freelancer->notifications()->get()->where('data.kind', 'amendment_proposed')->count());
        // Proposing changes nothing; the proposer cannot answer and the counterpart cannot withdraw.
        $this->post('/contracts/'.$contract->id.'/revisions', $revision)->assertSessionHasErrors('revision');
        $this->patch($path, ['amendment' => $amendment->id, 'action' => 'accept'])->assertForbidden();
        $this->actingAs($freelancer)->patch($path, ['amendment' => $amendment->id, 'action' => 'withdraw'])->assertForbidden();
        $this->get('/contracts/'.$contract->id)->assertInertia(fn (Assert $page) => $page->where('amendments.0.status', 'pending')
            ->where('amendments.0.mine', false)->where('amendments.0.extra_rounds', 1)->missing('amendments.0.proposer_id')->where('contract.revision_rounds', 0));
        // A page showing another proposal cannot answer this one.
        $this->patch($path, ['amendment' => $amendment->id + 1, 'action' => 'accept'])->assertSessionHasErrors('amendment');
        $this->patch($path, ['amendment' => $amendment->id, 'action' => 'accept'])->assertSessionHasNoErrors();
        $this->patch($path, ['amendment' => $amendment->id, 'action' => 'accept'])->assertSessionHasErrors('amendment');

        $fresh = $contract->fresh();
        $this->assertSame([1, 0, '750.25'], [$fresh->extra_rounds, $fresh->agreement['revision_rounds'], $fresh->agreement['amount']]);
        $this->assertSame(1, $client->notifications()->get()->where('data.kind', 'amendment_accepted')->count());
        $this->actingAs($client)->post('/contracts/'.$contract->id.'/revisions', $revision)->assertSessionHasNoErrors();
        $this->assertSame(['revision_requested', 1], [$contract->fresh()->status, $contract->fresh()->revisions_used]);
        $this->get('/contracts/'.$contract->id)->assertInertia(fn (Assert $page) => $page->where('contract.revision_rounds', 1)
            ->where('amendments.0.status', 'accepted')->where('activity', fn ($events) => collect($events)->pluck('kind')->contains('amendment_accepted')));
    }

    public function test_a_date_belongs_to_its_step_and_a_stale_acceptance_is_refused(): void
    {
        [$client, $freelancer, $contract] = $this->contract(rounds: 2);
        $path = '/contracts/'.$contract->id.'/amendments';
        $original = $contract->delivery_due_at;
        $this->actingAs($freelancer)->post($path, ['reason' => 'The client sent the assets late.', 'due_at' => now()->subDay()->toIso8601String()])->assertSessionHasErrors('due_at');
        // Declined and withdrawn proposals change nothing and free the slot.
        $this->post($path, ['reason' => 'The client sent the assets late.', 'due_at' => now()->addDays(20)->toIso8601String()])->assertSessionHasNoErrors();
        $this->actingAs($client)->patch($path, ['amendment' => ContractAmendment::query()->latest('id')->value('id'), 'action' => 'decline'])->assertSessionHasNoErrors();
        $this->actingAs($freelancer)->post($path, ['reason' => 'The client sent the assets late.', 'due_at' => now()->addDays(21)->toIso8601String()])->assertSessionHasNoErrors();
        $this->patch($path, ['amendment' => ContractAmendment::query()->latest('id')->value('id'), 'action' => 'withdraw'])->assertSessionHasNoErrors();
        $this->assertTrue($original->equalTo($contract->fresh()->delivery_due_at));
        $this->assertSame(['declined', 'withdrawn'], ContractAmendment::query()->orderBy('id')->pluck('status')->all());

        // Accepted: the first delivery date moves and the earlier one is kept on the proposal.
        $due = now()->addDays(22)->startOfSecond();
        $this->post($path, ['reason' => 'The client sent the assets late.', 'due_at' => $due->toIso8601String()])->assertSessionHasNoErrors();
        $accepted = ContractAmendment::query()->latest('id')->firstOrFail();
        $this->actingAs($client)->patch($path, ['amendment' => $accepted->id, 'action' => 'accept'])->assertSessionHasNoErrors();
        $this->assertTrue($due->equalTo($contract->fresh()->delivery_due_at));
        $this->assertTrue($original->equalTo($accepted->fresh()->previous_due_at));
        $this->assertSame('delivery', $accepted->date_kind);

        // A first-delivery date proposed before the delivery cannot bind after it.
        $this->post($path, ['reason' => 'We need the work a little later.', 'due_at' => now()->addDays(25)->toIso8601String()])->assertSessionHasNoErrors();
        $stale = ContractAmendment::query()->latest('id')->firstOrFail();
        $submission = $this->deliver($freelancer, $contract);
        $this->patch($path, ['amendment' => $stale->id, 'action' => 'accept'])->assertSessionHasErrors('amendment');
        $this->assertTrue($due->equalTo($contract->fresh()->delivery_due_at));
        $this->patch($path, ['amendment' => $stale->id, 'action' => 'decline'])->assertSessionHasNoErrors();

        // Q81: a revision date is its own proposal, and a new round starts without one.
        $this->actingAs($client)->post('/contracts/'.$contract->id.'/revisions', ['submission' => $submission->id, 'changes' => 'Please correct the Arabic layout and the totals.']);
        $revisionDue = now()->addDays(5)->startOfSecond();
        $this->post($path, ['reason' => 'These changes should take a few days.', 'due_at' => $revisionDue->toIso8601String()])->assertSessionHasNoErrors();
        $revision = ContractAmendment::query()->latest('id')->firstOrFail();
        $this->assertSame('revision', $revision->date_kind);
        $this->actingAs($freelancer)->patch($path, ['amendment' => $revision->id, 'action' => 'accept'])->assertSessionHasNoErrors();
        $this->assertTrue($revisionDue->equalTo($contract->fresh()->revision_due_at));
        $this->assertTrue($due->equalTo($contract->fresh()->delivery_due_at));
        $this->travel(6)->days();
        $this->get('/contracts/'.$contract->id)->assertInertia(fn (Assert $page) => $page->where('contract.revision_overdue', true)->where('contract.overdue', false));
        $second = $this->deliver($freelancer, $contract);
        $this->actingAs($client)->post('/contracts/'.$contract->id.'/revisions', ['submission' => $second->id, 'changes' => 'One more correction to the totals, please.']);
        $this->assertNull($contract->fresh()->revision_due_at);
    }

    public function test_a_pending_cancellation_pauses_amendments_and_finished_work_closes_them(): void
    {
        [$client, $freelancer, $contract] = $this->contract();
        $path = '/contracts/'.$contract->id.'/amendments';
        $this->actingAs($client)->post($path, ['reason' => 'One more round is needed.', 'extra_rounds' => 2])->assertSessionHasNoErrors();
        $amendment = ContractAmendment::query()->firstOrFail();
        // Q87: nothing is proposed or answered while a cancellation request is open.
        $contract->forceFill(['status' => 'cancellation_pending'])->save();
        $this->actingAs($freelancer)->patch($path, ['amendment' => $amendment->id, 'action' => 'accept'])->assertSessionHasErrors('amendment');
        $this->actingAs($client)->patch($path, ['amendment' => $amendment->id, 'action' => 'withdraw'])->assertSessionHasErrors('amendment');
        $this->assertSame(0, $contract->fresh()->extra_rounds);
        $contract->forceFill(['status' => 'active'])->save();

        $submission = $this->deliver($freelancer, $contract);
        $this->actingAs($client)->post('/contracts/'.$contract->id.'/complete', ['submission' => $submission->id])->assertSessionHasNoErrors();
        $this->assertSame(['closed', null], [$amendment->fresh()->status, $amendment->fresh()->open_contract_id]);
        $this->post($path, ['reason' => 'A change after completion.', 'extra_rounds' => 1])->assertSessionHasErrors('amendment');
        $this->assertSame(0, $contract->fresh()->extra_rounds);
    }

    public function test_the_client_copies_a_cancelled_contracts_brief_into_a_new_draft(): void
    {
        [$client, $freelancer, $contract] = $this->contract();
        $source = Project::query()->findOrFail($contract->project_id);
        $source->skills()->sync(Skill::query()->limit(2)->pluck('id')->all());
        $this->actingAs($client)->post('/contracts/'.$contract->id.'/repost')->assertStatus(409);
        $contract->forceFill(['status' => 'cancelled', 'cancelled_at' => now()])->save();
        $this->actingAs($freelancer)->post('/contracts/'.$contract->id.'/repost')->assertNotFound();
        $this->assertSame(1, Project::query()->count());

        $this->actingAs($client)->post('/contracts/'.$contract->id.'/repost')->assertRedirect();
        $draft = Project::query()->latest('id')->firstOrFail();
        $this->assertNotSame($source->id, $draft->id);
        $this->assertSame(['draft', null, $client->id], [$draft->status, $draft->published_at, $draft->user_id]);
        $this->assertSame([$source->title, $source->description, $source->category_id, $source->budget_max], [$draft->title, $draft->description, $draft->category_id, $draft->budget_max]);
        $this->assertSame($source->skills()->pluck('skills.id')->all(), $draft->skills()->pluck('skills.id')->all());
        $this->assertTrue($draft->application_closes_at->isFuture());
        // Q79: the original project and contract stay as they were.
        $this->assertSame(['hired', 'cancelled'], [$source->fresh()->status, $contract->fresh()->status]);
        $this->assertSame(0, $draft->proposals()->count());
    }

    public function test_a_stripe_refund_retried_after_a_failure_is_not_a_replay_of_the_failed_request(): void
    {
        [$client, $freelancer, $contract] = $this->contract();
        config(['payments.stripe.secret' => 'sk_test_example']);
        $contract->forceFill(['status' => 'awaiting_payment', 'funded_at' => null, 'delivery_due_at' => null])->save();
        $refunds = 0;
        Http::fake(['*/v1/checkout/sessions' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1']),
            '*/v1/checkout/sessions/cs_test_1' => fn () => Http::response(['id' => 'cs_test_1', 'livemode' => false, 'status' => 'complete', 'payment_status' => 'paid',
                'amount_total' => 75025, 'currency' => 'usd', 'payment_intent' => 'pi_1', 'client_reference_id' => PaymentAttempt::query()->value('reference')]),
            '*/v1/refunds' => function () use (&$refunds) {
                return Http::response(++$refunds === 1 ? ['id' => 're_1', 'status' => 'failed', 'amount' => 75025, 'currency' => 'usd']
                    : ['id' => 're_2', 'status' => 'succeeded', 'amount' => 75025, 'currency' => 'usd']);
            }]);
        $this->actingAs($client)->post('/contracts/'.$contract->id.'/payments', ['provider' => 'stripe', 'client_token' => (string) Str::uuid()])->assertRedirect();
        $attempt = PaymentAttempt::query()->firstOrFail();
        $this->get('/payments/'.$attempt->id.'/return');
        $this->assertSame('active', $contract->fresh()->status);
        $this->post('/contracts/'.$contract->id.'/cancellation', ['reason' => 'We no longer need this work.']);
        $this->actingAs($freelancer)->patch('/contracts/'.$contract->id.'/cancellation', ['action' => 'accept']);
        $this->assertSame('cancellation_pending', $contract->fresh()->status);
        $this->post('/contracts/'.$contract->id.'/cancellation/refund')->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $contract->fresh()->status);
        $keys = Http::recorded(fn ($request) => $request->url() === 'https://api.stripe.com/v1/refunds')->map(fn (array $pair) => $pair[0]->header('Idempotency-Key')[0])->values()->all();
        $this->assertSame([$attempt->reference.'-refund', $attempt->reference.'-refund-re_1'], $keys);
    }
}

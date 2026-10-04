<?php

namespace Tests\Feature;

use App\Actions\Payments\ContractFunding;
use App\Models\Category;
use App\Models\Contract;
use App\Models\ContractCancellation;
use App\Models\Offer;
use App\Models\PaymentAttempt;
use App\Models\PaymentEvent;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\SimulatedPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContractCancellationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Real keys in a developer's .env must never reach the tests.
        config(['payments.simulator.enabled' => true, 'payments.stripe.secret' => null, 'payments.stripe.webhook_secret' => null,
            'payments.moyasar.secret' => null, 'payments.paypal.client_id' => null, 'payments.paypal.secret' => null]);
    }

    /** @return array{User, User, Contract} */
    private function contract(bool $funded = true): array
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
            'deliverables' => ['Application source code'], 'amount' => '750.25', 'duration_days' => 14, 'revision_rounds' => 2,
            'proposal_version' => 1, 'client_token' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->actingAs($freelancer)->patch('/offers/'.Offer::query()->latest('id')->value('id'), ['action' => 'accept', 'version' => 1])->assertSessionHasNoErrors();
        $contract = Contract::query()->latest('id')->firstOrFail();
        if ($funded) {
            $attempt = $this->start($client, $contract);
            $this->post('/payments/'.$attempt->id.'/simulator', ['decision' => 'approve']);
            $this->get('/payments/'.$attempt->id.'/return');
            $this->assertSame('active', $contract->fresh()->status);
        }

        return [$client, $freelancer, $contract];
    }

    private function start(User $client, Contract $contract): PaymentAttempt
    {
        $this->actingAs($client)->post('/contracts/'.$contract->id.'/payments', ['provider' => 'simulator', 'client_token' => (string) Str::uuid()])->assertSessionHasNoErrors();

        return PaymentAttempt::query()->latest('id')->firstOrFail();
    }

    public function test_an_unfunded_contract_is_cancelled_at_once_after_any_open_payment_is_settled(): void
    {
        [$client, $freelancer, $contract] = $this->contract(funded: false);
        $reason = ['reason' => 'The project was put on hold by our team.'];
        $stranger = User::factory()->create(['onboarding_completed_at' => now(), 'is_admin' => true]);
        $this->actingAs($stranger)->post('/contracts/'.$contract->id.'/cancellation', $reason)->assertNotFound();
        $attempt = $this->start($client, $contract);
        $this->actingAs($freelancer)->post('/contracts/'.$contract->id.'/cancellation', $reason)->assertSessionHasErrors('cancellation');
        $this->actingAs($client)->post('/payments/'.$attempt->id.'/cancel');
        $this->actingAs($freelancer)->post('/contracts/'.$contract->id.'/cancellation', $reason)->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $contract->fresh()->status);
        $this->assertNotNull($contract->fresh()->cancelled_at);
        $this->assertSame(1, $client->notifications()->where('data->kind', 'contract_cancelled')->count());
        // Nothing can be funded or cancelled again afterwards.
        $this->actingAs($client)->post('/contracts/'.$contract->id.'/payments', ['provider' => 'simulator', 'client_token' => (string) Str::uuid()])->assertSessionHasErrors('payment');
        $this->post('/contracts/'.$contract->id.'/cancellation', $reason)->assertSessionHasErrors('cancellation');
        $this->assertSame(0, ContractFunding::summary($client->id)['client']['awaiting']['count']);
    }

    public function test_a_funded_request_pauses_work_and_decline_or_withdrawal_restores_it(): void
    {
        [$client, $freelancer, $contract] = $this->contract();
        $delivery = ['message' => 'The complete application is ready for your review.', 'complete' => true];
        $this->actingAs($freelancer)->post('/contracts/'.$contract->id.'/deliveries', [...$delivery, 'client_token' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->actingAs($client)->post('/contracts/'.$contract->id.'/cancellation', ['reason' => 'We no longer need this work.'])->assertSessionHasNoErrors();
        $this->assertSame('cancellation_pending', $contract->fresh()->status);
        $this->assertSame(1, $freelancer->notifications()->where('data->kind', 'cancellation_requested')->count());
        // One unresolved request at a time; the requester cannot answer their own request.
        $this->actingAs($freelancer)->post('/contracts/'.$contract->id.'/cancellation', ['reason' => 'A second request while one is open.'])->assertSessionHasErrors('cancellation');
        $this->actingAs($client)->patch('/contracts/'.$contract->id.'/cancellation', ['action' => 'accept'])->assertForbidden();
        $this->actingAs($freelancer)->patch('/contracts/'.$contract->id.'/cancellation', ['action' => 'withdraw'])->assertForbidden();
        // Q87: formal actions wait; the page still opens for both.
        $submission = $contract->submissions()->value('id');
        $this->actingAs($client)->post('/contracts/'.$contract->id.'/complete', ['submission' => $submission])->assertSessionHasErrors('delivery');
        $this->post('/contracts/'.$contract->id.'/revisions', ['submission' => $submission, 'changes' => 'Please correct the Arabic layout and totals.'])->assertSessionHasErrors('delivery');
        $this->actingAs($freelancer)->get('/contracts/'.$contract->id)->assertInertia(fn (Assert $page) => $page
            ->where('cancellation.status', 'pending')->where('cancellation.mine', false)->missing('cancellation.requester_id')->missing('cancellation.refund_reference'));

        $this->patch('/contracts/'.$contract->id.'/cancellation', ['action' => 'decline'])->assertSessionHasNoErrors();
        $this->assertSame('submitted', $contract->fresh()->status);
        $this->patch('/contracts/'.$contract->id.'/cancellation', ['action' => 'decline'])->assertSessionHasErrors('cancellation');
        $this->post('/contracts/'.$contract->id.'/cancellation', ['reason' => 'I cannot continue with this project.'])->assertSessionHasNoErrors();
        $this->patch('/contracts/'.$contract->id.'/cancellation', ['action' => 'withdraw'])->assertSessionHasNoErrors();
        $this->assertSame('submitted', $contract->fresh()->status);
        $this->assertSame(['declined', 'withdrawn'], ContractCancellation::query()->orderBy('id')->pluck('status')->all());
        $this->assertSame('succeeded', PaymentAttempt::query()->value('status'));
    }

    public function test_acceptance_refunds_once_and_only_then_cancels(): void
    {
        [$client, $freelancer, $contract] = $this->contract();
        $this->actingAs($freelancer)->post('/contracts/'.$contract->id.'/cancellation', ['reason' => 'I cannot continue with this project.']);
        // The provider has lost the payment: the refund fails and nothing resumes or completes.
        SimulatedPayment::query()->update(['status' => 'declined']);
        $this->actingAs($client)->patch('/contracts/'.$contract->id.'/cancellation', ['action' => 'accept'])->assertSessionHasNoErrors();
        $this->assertSame('cancellation_pending', $contract->fresh()->status);
        $this->assertSame(['accepted', 'failed'], [ContractCancellation::query()->value('status'), ContractCancellation::query()->value('refund_status')]);
        $this->actingAs($freelancer)->post('/contracts/'.$contract->id.'/deliveries', ['message' => 'The complete application is ready for your review.', 'complete' => true, 'client_token' => (string) Str::uuid()])->assertSessionHasErrors('delivery');
        $this->patch('/contracts/'.$contract->id.'/cancellation', ['action' => 'withdraw'])->assertSessionHasErrors('cancellation');
        $this->get('/contracts/'.$contract->id)->assertInertia(fn (Assert $page) => $page->where('cancellation.refund_status', 'failed')->where('cancellation.refund_failure', 'unknown_payment'));

        // The retry succeeds; repeating it records one refund.
        SimulatedPayment::query()->update(['status' => 'approved']);
        $this->post('/contracts/'.$contract->id.'/cancellation/refund')->assertSessionHasNoErrors();
        $this->post('/contracts/'.$contract->id.'/cancellation/refund')->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $contract->fresh()->status);
        $this->assertSame('refunded', SimulatedPayment::query()->value('status'));
        $this->assertSame('refunded', PaymentAttempt::query()->value('status'));
        $this->assertSame(1, PaymentEvent::query()->where('type', 'refund')->where('amount_minor', 75025)->count());
        $this->assertSame(['refunded', 'succeeded'], [ContractCancellation::query()->value('status'), ContractCancellation::query()->value('refund_status')]);
        $this->assertSame(1, $client->notifications()->where('data->kind', 'contract_refunded')->count());
        $this->assertSame(1, $freelancer->notifications()->where('data->kind', 'contract_refunded')->count());
        $this->assertSame(0, ContractFunding::summary($client->id)['client']['funded']['count']);
        $this->get('/contracts/'.$contract->id)->assertInertia(fn (Assert $page) => $page->where('contract.status', 'cancelled')
            ->where('activity.0.kind', 'cancelled')->where('reviews', null));
        $this->actingAs($client)->put('/contracts/'.$contract->id.'/review', ['rating' => 5, 'body' => 'Reviews are only for completed contracts.'])->assertSessionHasErrors('review');
    }

    public function test_a_refund_for_another_amount_is_not_accepted(): void
    {
        [$client, $freelancer, $contract] = $this->contract();
        $this->actingAs($client)->post('/contracts/'.$contract->id.'/cancellation', ['reason' => 'We no longer need this work.']);
        SimulatedPayment::query()->update(['amount_minor' => 100]);
        $this->actingAs($freelancer)->patch('/contracts/'.$contract->id.'/cancellation', ['action' => 'accept']);
        $this->assertSame('cancellation_pending', $contract->fresh()->status);
        $this->assertSame(['failed', 'mismatch'], [ContractCancellation::query()->value('refund_status'), ContractCancellation::query()->value('refund_failure')]);
        $this->assertSame(0, PaymentEvent::query()->where('type', 'refund')->count());
    }

    public function test_a_stripe_callback_must_be_signed_and_only_triggers_a_provider_check(): void
    {
        [$client, , $contract] = $this->contract(funded: false);
        config(['payments.stripe.secret' => 'sk_test_example']);
        $paid = false;
        Http::fake(['*/v1/checkout/sessions' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1']),
            '*/v1/checkout/sessions/cs_test_1' => function () use (&$paid) {
                return Http::response(['id' => 'cs_test_1', 'livemode' => false, 'status' => $paid ? 'complete' : 'open', 'payment_status' => $paid ? 'paid' : 'unpaid',
                    'amount_total' => 75025, 'currency' => 'usd', 'payment_intent' => 'pi_1', 'client_reference_id' => PaymentAttempt::query()->value('reference')]);
            },
            '*/v1/refunds' => Http::response(['id' => 're_1', 'status' => 'succeeded', 'amount' => 75025, 'currency' => 'usd'])]);
        $this->actingAs($client)->post('/contracts/'.$contract->id.'/payments', ['provider' => 'stripe', 'client_token' => (string) Str::uuid()])->assertRedirect();
        auth()->logout();
        $payload = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_test_1', 'payment_status' => 'paid']]]);
        $send = fn (string $signature) => $this->call('POST', '/payments/webhooks/stripe', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], $payload);
        // Off without a signing secret; then unsigned, stale and forged calls are refused.
        $send('t='.time().',v1=anything')->assertNotFound();
        config(['payments.stripe.webhook_secret' => 'whsec_test']);
        $sign = fn (int $time, string $secret = 'whsec_test') => 't='.$time.',v1='.hash_hmac('sha256', $time.'.'.$payload, $secret);
        $send('')->assertStatus(400);
        $send($sign(time() - 3600))->assertStatus(400);
        $send($sign(time(), 'whsec_other'))->assertStatus(400);
        // A genuine event whose payload says paid still funds nothing while Stripe says unpaid.
        $send($sign(time()))->assertNoContent();
        $this->assertSame('awaiting_payment', $contract->fresh()->status);
        $paid = true;
        $send($sign(time()))->assertNoContent();
        $send($sign(time()))->assertNoContent();
        $this->assertSame('active', $contract->fresh()->status);
        $this->assertSame(1, PaymentEvent::query()->where('type', 'capture')->count());

        // The refund names the verified payment intent and is idempotent on the attempt.
        $attempt = PaymentAttempt::query()->firstOrFail();
        $this->actingAs($client)->post('/contracts/'.$contract->id.'/cancellation', ['reason' => 'We no longer need this work.']);
        $this->actingAs(User::query()->findOrFail($contract->freelancer_id))->patch('/contracts/'.$contract->id.'/cancellation', ['action' => 'accept']);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.stripe.com/v1/refunds' && $request['payment_intent'] === 'pi_1'
            && $request->header('Idempotency-Key') === [$attempt->reference.'-refund']);
        $this->assertSame('cancelled', $contract->fresh()->status);
        $this->assertDatabaseHas('payment_events', ['provider' => 'stripe', 'type' => 'refund', 'event_reference' => 'refund:re_1']);
    }

    public function test_the_scheduled_check_settles_a_payment_whose_payer_never_returned(): void
    {
        [$client, , $contract] = $this->contract(funded: false);
        $attempt = $this->start($client, $contract);
        $this->post('/payments/'.$attempt->id.'/simulator', ['decision' => 'approve']);
        $this->assertSame('awaiting_payment', $contract->fresh()->status);
        $this->artisan('payments:reconcile')->assertSuccessful();
        $this->assertSame('active', $contract->fresh()->status);
    }
}

<?php

namespace Tests\Feature;

use App\Actions\Payments\ContractFunding;
use App\Models\Category;
use App\Models\Contract;
use App\Models\Offer;
use App\Models\PaymentAttempt;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\SimulatedPayment;
use App\Models\User;
use App\Payments\Money;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContractFundingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Real keys in a developer's .env must never reach the tests.
        config(['payments.simulator.enabled' => true, 'payments.stripe.secret' => null, 'payments.moyasar.secret' => null, 'payments.paypal.client_id' => null, 'payments.paypal.secret' => null]);
    }

    /** @return array{User, User, Contract} */
    private function contract(): array
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

        return [$client, $freelancer, Contract::query()->latest('id')->firstOrFail()];
    }

    private function start(User $client, Contract $contract, ?string $token = null): PaymentAttempt
    {
        $this->actingAs($client)->post('/contracts/'.$contract->id.'/payments', ['provider' => 'simulator', 'client_token' => $token ?? (string) Str::uuid()])
            ->assertSessionHasNoErrors()->assertRedirect();

        return PaymentAttempt::query()->latest('id')->firstOrFail();
    }

    public function test_only_the_client_starts_funding_and_strangers_see_nothing(): void
    {
        [$client, $freelancer, $contract] = $this->contract();
        $payload = ['provider' => 'simulator', 'client_token' => (string) Str::uuid()];
        $this->actingAs($freelancer)->post('/contracts/'.$contract->id.'/payments', $payload)->assertForbidden();
        $stranger = User::factory()->create(['onboarding_completed_at' => now(), 'is_admin' => true]);
        $this->actingAs($stranger)->post('/contracts/'.$contract->id.'/payments', $payload)->assertNotFound();
        $attempt = $this->start($client, $contract);
        $this->actingAs($stranger)->get('/payments/'.$attempt->id.'/return')->assertNotFound();
        $this->post('/payments/'.$attempt->id.'/check')->assertNotFound();
        $this->get('/finance')->assertInertia(fn (Assert $page) => $page->has('contracts.data', 0)->has('attempts', 0)->where('summary.client.awaiting.count', 0));
        // The freelancer may follow status but can neither use the payer's page nor cancel.
        $this->actingAs($freelancer)->get('/payments/'.$attempt->id.'/simulator')->assertNotFound();
        $this->post('/payments/'.$attempt->id.'/simulator', ['decision' => 'approve'])->assertNotFound();
        $this->post('/payments/'.$attempt->id.'/cancel')->assertForbidden();
        $this->assertSame('created', SimulatedPayment::query()->value('status'));
        $this->get('/contracts/'.$contract->id)->assertInertia(fn (Assert $page) => $page->where('payment.status', 'pending')->has('providers', 0)
            ->missing('payment.client_token')->missing('payment.provider_reference')->missing('payment.payer_id'));
    }

    public function test_a_browser_return_alone_never_activates_the_contract(): void
    {
        [$client, , $contract] = $this->contract();
        $attempt = $this->start($client, $contract);
        $this->get('/payments/'.$attempt->id.'/return')->assertRedirect('/contracts/'.$contract->id);
        $this->post('/payments/'.$attempt->id.'/check')->assertRedirect();
        $this->assertSame('pending', $attempt->fresh()->status);
        $this->assertSame('awaiting_payment', $contract->fresh()->status);
        $this->assertNull($contract->fresh()->funded_at);
    }

    public function test_verified_approval_activates_once_and_starts_the_delivery_clock(): void
    {
        $this->freezeSecond();
        [$client, $freelancer, $contract] = $this->contract();
        $attempt = $this->start($client, $contract);
        $this->assertSame(75025, $attempt->amount_minor);
        $this->post('/payments/'.$attempt->id.'/simulator', ['decision' => 'approve'])->assertRedirect('/payments/'.$attempt->id.'/return');
        $this->assertSame('awaiting_payment', $contract->fresh()->status);
        $this->get('/payments/'.$attempt->id.'/return')->assertRedirect('/contracts/'.$contract->id);
        $this->get('/payments/'.$attempt->id.'/return')->assertRedirect();
        $this->post('/payments/'.$attempt->id.'/check')->assertRedirect();
        $contract->refresh();
        $this->assertSame('active', $contract->status);
        $this->assertTrue($contract->funded_at->equalTo(now()));
        $this->assertTrue($contract->delivery_due_at->equalTo(now()->addDays(14)));
        $this->assertSame('succeeded', $attempt->fresh()->status);
        $this->assertNull($attempt->fresh()->open_contract_id);
        $this->assertDatabaseCount('payment_events', 1);
        $this->assertSame(1, $freelancer->notifications()->where('data', 'like', '%contract_funded%')->count());
        $this->post('/contracts/'.$contract->id.'/payments', ['provider' => 'simulator', 'client_token' => (string) Str::uuid()])->assertSessionHasErrors('payment');
        $this->assertDatabaseCount('payment_attempts', 1);
        $this->get('/finance')->assertInertia(fn (Assert $page) => $page->where('summary.client.funded.count', 1)->where('summary.client.funded.total', '750.25')
            ->where('summary.client.awaiting.count', 0)->where('summary.freelancer.funded.count', 0)->where('contracts.data.0.payment.status', 'succeeded'));
        $this->actingAs($freelancer)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('overview.finance.funded.total', '750.25'));
    }

    public function test_start_is_idempotent_and_keeps_one_open_attempt(): void
    {
        [$client, , $contract] = $this->contract();
        $token = (string) Str::uuid();
        $first = $this->start($client, $contract, $token);
        $this->assertSame($first->id, $this->start($client, $contract, $token)->id);
        $this->assertSame($first->id, $this->start($client, $contract)->id);
        $this->assertDatabaseCount('payment_attempts', 1);
        $this->assertDatabaseCount('simulated_payments', 1);
        $duplicate = $first->replicate();
        $duplicate->forceFill(['reference' => (string) Str::uuid(), 'client_token' => (string) Str::uuid(), 'provider_reference' => null]);
        $this->expectException(QueryException::class);
        $duplicate->save();
    }

    public function test_decline_and_cancel_free_the_slot_without_funding(): void
    {
        [$client, , $contract] = $this->contract();
        $declined = $this->start($client, $contract);
        $this->post('/payments/'.$declined->id.'/simulator', ['decision' => 'decline']);
        $this->get('/payments/'.$declined->id.'/return');
        $this->assertSame(['failed', 'declined'], [$declined->fresh()->status, $declined->fresh()->failure_reason]);
        $cancelled = $this->start($client, $contract);
        $this->assertNotSame($declined->id, $cancelled->id);
        $this->post('/payments/'.$cancelled->id.'/cancel')->assertRedirect();
        $this->assertSame('cancelled', $cancelled->fresh()->status);
        // A decision arriving after cancellation changes nothing on either side.
        $this->post('/payments/'.$cancelled->id.'/simulator', ['decision' => 'approve']);
        $this->get('/payments/'.$cancelled->id.'/return');
        $this->assertSame('cancelled', $cancelled->fresh()->status);
        $this->assertSame('awaiting_payment', $contract->fresh()->status);
        $this->assertDatabaseCount('payment_events', 0);
    }

    public function test_cancelling_after_provider_approval_funds_instead_of_discarding_the_payment(): void
    {
        [$client, , $contract] = $this->contract();
        $attempt = $this->start($client, $contract);
        $this->post('/payments/'.$attempt->id.'/simulator', ['decision' => 'approve']);
        $this->post('/payments/'.$attempt->id.'/cancel')->assertRedirect();
        $this->assertSame('succeeded', $attempt->fresh()->status);
        $this->assertSame('active', $contract->fresh()->status);
    }

    public function test_wrong_amount_currency_or_reference_is_rejected(): void
    {
        foreach ([['amount_minor' => 100], ['currency' => 'EUR'], ['application_reference' => (string) Str::uuid()]] as $tampered) {
            [$client, , $contract] = $this->contract();
            $attempt = $this->start($client, $contract);
            SimulatedPayment::query()->where('reference', $attempt->provider_reference)->update([...$tampered, 'status' => 'approved']);
            $this->get('/payments/'.$attempt->id.'/return');
            $this->assertSame(['failed', 'mismatch'], [$attempt->fresh()->status, $attempt->fresh()->failure_reason]);
            $this->assertSame('awaiting_payment', $contract->fresh()->status);
        }
        $this->assertDatabaseCount('payment_events', 0);
    }

    public function test_either_suspension_pauses_new_funding_but_an_attempt_underway_still_reconciles(): void
    {
        [$client, $freelancer, $contract] = $this->contract();
        $freelancer->forceFill(['status' => 'suspended'])->save();
        $this->actingAs($client)->post('/contracts/'.$contract->id.'/payments', ['provider' => 'simulator', 'client_token' => (string) Str::uuid()])->assertSessionHasErrors('payment');
        $this->assertDatabaseCount('payment_attempts', 0);
        $this->get('/contracts/'.$contract->id)->assertInertia(fn (Assert $page) => $page->where('funding_paused', true));
        $freelancer->forceFill(['status' => 'active'])->save();
        $attempt = $this->start($client, $contract);
        $this->post('/payments/'.$attempt->id.'/simulator', ['decision' => 'approve']);
        $freelancer->forceFill(['status' => 'suspended'])->save();
        $this->get('/payments/'.$attempt->id.'/return');
        $this->assertSame('active', $contract->fresh()->status);
    }

    public function test_provider_failure_starts_nothing_and_the_simulator_is_hidden_when_disabled(): void
    {
        [$client, , $contract] = $this->contract();
        config(['payments.simulator.enabled' => false]);
        $this->actingAs($client)->post('/contracts/'.$contract->id.'/payments', ['provider' => 'simulator', 'client_token' => (string) Str::uuid()])->assertSessionHasErrors('provider');
        $this->get('/contracts/'.$contract->id)->assertInertia(fn (Assert $page) => $page->has('providers', 0));
        config(['payments.paypal.client_id' => 'id', 'payments.paypal.secret' => 'secret']);
        Http::fake(['*' => Http::response([], 503)]);
        $this->post('/contracts/'.$contract->id.'/payments', ['provider' => 'paypal', 'client_token' => (string) Str::uuid()])->assertSessionHasErrors('payment');
        $attempt = PaymentAttempt::query()->firstOrFail();
        $this->assertSame(['failed', 'provider_unavailable', null], [$attempt->status, $attempt->failure_reason, $attempt->open_contract_id]);
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api-m.sandbox.paypal.com/'));
    }

    public function test_paypal_sandbox_order_is_captured_and_checked_before_activation(): void
    {
        config(['payments.paypal.client_id' => 'id', 'payments.paypal.secret' => 'secret']);
        [$client, , $contract] = $this->contract();
        $order = fn (string $status, array $extra = []) => ['id' => 'ORDER-1', 'status' => $status, 'links' => [['rel' => 'payer-action', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER-1']], ...$extra];
        Http::fake(['*/v1/oauth2/token' => Http::response(['access_token' => 'token']),
            '*/v2/checkout/orders' => Http::response($order('PAYER_ACTION_REQUIRED')),
            '*/v2/checkout/orders/ORDER-1' => Http::sequence()->push($order('PAYER_ACTION_REQUIRED'))->push($order('APPROVED')),
            '*/v2/checkout/orders/ORDER-1/capture' => fn () => Http::response($order('COMPLETED', ['purchase_units' => [['payments' => ['captures' => [[
                'id' => 'CAPTURE-1', 'status' => 'COMPLETED', 'custom_id' => PaymentAttempt::query()->value('reference'),
                'amount' => ['currency_code' => 'USD', 'value' => '750.25']]]]]]])),
        ]);
        $this->actingAs($client)->post('/contracts/'.$contract->id.'/payments', ['provider' => 'paypal', 'client_token' => (string) Str::uuid()])
            ->assertRedirect('https://www.sandbox.paypal.com/checkoutnow?token=ORDER-1');
        $attempt = PaymentAttempt::query()->firstOrFail();
        $this->assertSame('ORDER-1', $attempt->provider_reference);
        $this->get('/payments/'.$attempt->id.'/return');
        $this->assertSame('awaiting_payment', $contract->fresh()->status);
        $this->get('/payments/'.$attempt->id.'/return');
        $this->assertSame('active', $contract->fresh()->status);
        $this->assertDatabaseHas('payment_events', ['provider' => 'paypal', 'event_reference' => 'CAPTURE-1', 'amount_minor' => 75025]);
    }

    public function test_stripe_test_session_must_be_paid_and_not_live_before_activation(): void
    {
        [$client, , $contract] = $this->contract();
        config(['payments.stripe.secret' => 'sk_live_refused']);
        $this->actingAs($client)->post('/contracts/'.$contract->id.'/payments', ['provider' => 'stripe', 'client_token' => (string) Str::uuid()])->assertSessionHasErrors('provider');
        config(['payments.stripe.secret' => 'sk_test_example']);
        $reads = 0;
        $session = fn (array $state) => ['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1', 'livemode' => false,
            'amount_total' => 75025, 'currency' => 'usd', 'payment_intent' => 'pi_1', 'client_reference_id' => PaymentAttempt::query()->value('reference'), ...$state];
        $created = 0;
        Http::fake(['*/v1/checkout/sessions' => function () use ($session, &$created) {
            return Http::response(++$created === 1 ? $session(['status' => 'open', 'payment_status' => 'unpaid']) : ['id' => 'cs_test_2', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_2']);
        },
            '*/v1/checkout/sessions/cs_test_1' => function () use ($session, &$reads) {
                return Http::response($session(++$reads === 1 ? ['status' => 'open', 'payment_status' => 'unpaid'] : ['status' => 'complete', 'payment_status' => 'paid', 'livemode' => true]));
            },
            '*/v1/checkout/sessions/cs_test_2' => fn () => Http::response(['id' => 'cs_test_2', 'livemode' => false, 'status' => 'complete', 'payment_status' => 'paid',
                'amount_total' => 75025, 'currency' => 'usd', 'payment_intent' => 'pi_2', 'client_reference_id' => PaymentAttempt::query()->latest('id')->value('reference')])]);
        $this->post('/contracts/'.$contract->id.'/payments', ['provider' => 'stripe', 'client_token' => (string) Str::uuid()])
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_1');
        $attempt = PaymentAttempt::query()->firstOrFail();
        Http::assertSent(fn ($request) => $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
            && $request['line_items'][0]['price_data']['unit_amount'] == 75025 && $request->header('Idempotency-Key') === [$attempt->reference]);
        $this->get('/payments/'.$attempt->id.'/return');
        $this->assertSame('pending', $attempt->fresh()->status);
        // A session reported as live is refused even when it says paid.
        $this->get('/payments/'.$attempt->id.'/return');
        $this->assertSame(['failed', 'mismatch'], [$attempt->fresh()->status, $attempt->fresh()->failure_reason]);
        $this->assertSame('awaiting_payment', $contract->fresh()->status);

        $this->post('/contracts/'.$contract->id.'/payments', ['provider' => 'stripe', 'client_token' => (string) Str::uuid()])->assertRedirect();
        $this->get('/payments/'.PaymentAttempt::query()->latest('id')->value('id').'/return');
        $this->assertSame('active', $contract->fresh()->status);
        $this->assertDatabaseHas('payment_events', ['provider' => 'stripe', 'event_reference' => 'pi_2']);
    }

    public function test_moyasar_test_invoice_must_be_paid_and_resumes_without_a_second_invoice(): void
    {
        [$client, , $contract] = $this->contract();
        config(['payments.moyasar.secret' => 'sk_live_refused']);
        $this->actingAs($client)->post('/contracts/'.$contract->id.'/payments', ['provider' => 'moyasar', 'client_token' => (string) Str::uuid()])->assertSessionHasErrors('provider');
        config(['payments.moyasar.secret' => 'sk_test_example']);
        $status = 'initiated';
        $invoice = function () use (&$status) {
            return Http::response(['id' => 'inv-1', 'status' => $status, 'amount' => 75025, 'currency' => 'USD',
                'url' => 'https://checkout.moyasar.com/invoices/inv-1', 'metadata' => ['reference' => PaymentAttempt::query()->value('reference')]]);
        };
        Http::fake(['https://api.moyasar.com/v1/invoices' => $invoice, 'https://api.moyasar.com/v1/invoices/inv-1' => $invoice]);
        $this->post('/contracts/'.$contract->id.'/payments', ['provider' => 'moyasar', 'client_token' => (string) Str::uuid()])
            ->assertRedirect('https://checkout.moyasar.com/invoices/inv-1');
        $attempt = PaymentAttempt::query()->firstOrFail();
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['amount'] === 75025 && $request['currency'] === 'USD'
            && $request['metadata']['reference'] === $attempt->reference && $request->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_example:')));
        // Continuing reads the existing invoice; it never creates a second one.
        $this->post('/contracts/'.$contract->id.'/payments', ['provider' => 'moyasar', 'client_token' => (string) Str::uuid()])->assertRedirect('https://checkout.moyasar.com/invoices/inv-1');
        Http::assertSentCount(2);
        $this->get('/payments/'.$attempt->id.'/return');
        $this->assertSame('awaiting_payment', $contract->fresh()->status);
        $status = 'paid';
        $this->get('/payments/'.$attempt->id.'/return');
        $this->assertSame('active', $contract->fresh()->status);
        $this->assertDatabaseHas('payment_events', ['provider' => 'moyasar', 'event_reference' => 'inv-1', 'amount_minor' => 75025]);
    }

    public function test_money_converts_without_floating_point(): void
    {
        $this->assertSame([75025, 100, 1990, 100000000], [Money::minor('750.25'), Money::minor('1'), Money::minor('19.9'), Money::minor('1000000.00')]);
        $this->assertSame('0.05', Money::decimal(5));
        $this->assertTrue(ContractFunding::summary(0)['client']['awaiting']['total'] === '0.00');
    }
}

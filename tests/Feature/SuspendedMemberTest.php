<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Contract;
use App\Models\ContractAmendment;
use App\Models\ContractSubmission;
use App\Models\ContractSubmissionFile;
use App\Models\ConversationMessage;
use App\Models\Offer;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Q64: a suspended member keeps restricted access to existing work and loses new marketplace actions. */
class SuspendedMemberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['payments.simulator.enabled' => true, 'payments.stripe.secret' => null, 'payments.moyasar.secret' => null]);
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
            'deliverables' => ['Application source code'], 'amount' => '750.25', 'duration_days' => 14, 'revision_rounds' => 1,
            'proposal_version' => 1, 'client_token' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->actingAs($freelancer)->patch('/offers/'.Offer::query()->latest('id')->value('id'), ['action' => 'accept', 'version' => 1])->assertSessionHasNoErrors();
        $contract = Contract::query()->latest('id')->firstOrFail();
        if ($funded) {
            $contract->forceFill(['status' => 'active', 'funded_at' => now(), 'delivery_due_at' => now()->addDays(14)])->save();
        }

        return [$client, $freelancer, $contract];
    }

    /** @return array<string, mixed> */
    private function delivery(array $extra = []): array
    {
        return ['message' => 'The complete application is ready for your review.', 'complete' => true, 'client_token' => (string) Str::uuid(), ...$extra];
    }

    public function test_a_suspended_freelancer_keeps_the_existing_contract_and_its_conversation(): void
    {
        [$client, $freelancer, $contract] = $this->contract();
        $freelancer->forceFill(['status' => 'suspended'])->save();
        $url = '/contracts/'.$contract->id;
        $thread = '/messages/'.$contract->conversation_id;

        // Reading: the contract, both lists and the thread, which stays writable.
        $this->actingAs($freelancer)->get('/contracts')->assertOk()->assertInertia(fn (Assert $page) => $page->has('contracts.data', 1));
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->where('contract.status', 'active')->where('auth.user.status', 'suspended'));
        $this->get('/messages')->assertOk();
        $this->get($thread)->assertOk()->assertInertia(fn (Assert $page) => $page->where('writable', true));

        // Messages: send and correct.
        $this->post($thread, ['body' => 'I can still write here.', 'client_token' => (string) Str::uuid()])->assertRedirect();
        $message = ConversationMessage::query()->where('sender_id', $freelancer->id)->latest('id')->firstOrFail();
        $this->patch('/messages/items/'.$message->id, ['body' => 'I can still correct this.', 'version' => 1])->assertSessionHasNoErrors();
        $this->assertSame('I can still correct this.', $message->fresh()->body);

        // Agreed changes and cancellation requests.
        $this->post($url.'/amendments', ['reason' => 'One more round would help here.', 'extra_rounds' => 1])->assertSessionHasNoErrors();
        $amendment = ContractAmendment::query()->latest('id')->firstOrFail();
        $this->patch($url.'/amendments', ['amendment' => $amendment->id, 'action' => 'withdraw'])->assertSessionHasNoErrors();
        $this->post($url.'/cancellation', ['reason' => 'I cannot continue this work.'])->assertSessionHasNoErrors();
        $this->assertSame('cancellation_pending', $contract->fresh()->status);
        $this->patch($url.'/cancellation', ['action' => 'withdraw'])->assertSessionHasNoErrors();
        $this->assertSame('active', $contract->fresh()->status);

        // Delivery with a file, the client's revision, redelivery, completion and a review.
        $this->post($url.'/deliveries', $this->delivery(['files' => [UploadedFile::fake()->create('handover.pdf', 120, 'application/pdf')]]))->assertSessionHasNoErrors();
        $first = ContractSubmission::query()->latest('id')->firstOrFail();
        $this->get($url.'/files/'.ContractSubmissionFile::query()->value('id'))->assertOk();
        $this->actingAs($client)->post($url.'/revisions', ['submission' => $first->id, 'changes' => 'Please correct the Arabic layout and the totals.'])->assertSessionHasNoErrors();
        $this->actingAs($freelancer)->post($url.'/deliveries', $this->delivery())->assertSessionHasNoErrors();
        $this->actingAs($client)->post($url.'/complete', ['submission' => ContractSubmission::query()->latest('id')->value('id')])->assertSessionHasNoErrors();
        $this->actingAs($freelancer)->put($url.'/review', ['rating' => 5, 'body' => 'A precise brief and quick answers.'])->assertSessionHasNoErrors();
        $this->assertSame('completed', $contract->fresh()->status);

        // Other pages a suspended member still opens.
        foreach (['/dashboard', '/finance', '/offers', '/my-reports', '/blocked-accounts', '/my-portfolio'] as $page) {
            $this->get($page)->assertOk();
        }
        // The proposal behind the contract stays readable with nothing to act on; the proposal list does not.
        $this->get('/proposals/'.$contract->proposal_id)->assertOk()->assertInertia(fn (Assert $page) => $page->where('author', true)->where('canEdit', false));
        $this->get('/my-proposals')->assertForbidden();
        // New marketplace actions are refused.
        $this->post('/my-projects')->assertForbidden();
        $this->post('/my-portfolio', [])->assertForbidden();
        $open = new Project;
        $open->forceFill(['user_id' => User::factory()->create(['onboarding_completed_at' => now()])->id, 'category_id' => Category::query()->value('id'),
            'title' => 'Another project', 'description' => 'A real project brief.', 'budget_min' => 100, 'budget_max' => 500,
            'status' => 'published', 'published_at' => now()->subDay(), 'application_closes_at' => now()->addDay()])->save();
        $this->get('/jobs/'.$open->id.'/apply')->assertForbidden();
        $this->putJson('/jobs/'.$open->id.'/proposal', ['action' => 'save', 'version' => 0])->assertForbidden();

        // Blocking the counterpart is still allowed and the thread stays writable for both.
        $this->post($thread.'/block')->assertRedirect();
        $this->assertSame(1, DB::table('user_blocks')->where('user_id', $freelancer->id)->count());
        $this->actingAs($client)->post($thread, ['body' => 'The contract thread still works.', 'client_token' => (string) Str::uuid()])->assertRedirect();
    }

    public function test_a_suspended_client_keeps_the_existing_contract_and_reads_their_own_project(): void
    {
        [$client, $freelancer, $contract] = $this->contract();
        $client->forceFill(['status' => 'suspended'])->save();
        $url = '/contracts/'.$contract->id;
        $thread = '/messages/'.$contract->conversation_id;

        $this->actingAs($client)->get($url)->assertOk();
        $this->get($thread)->assertOk()->assertInertia(fn (Assert $page) => $page->where('writable', true));
        $this->post($thread, ['body' => 'I can still write here.', 'client_token' => (string) Str::uuid()])->assertRedirect();

        $this->actingAs($freelancer)->post($url.'/deliveries', $this->delivery())->assertSessionHasNoErrors();
        $first = ContractSubmission::query()->latest('id')->firstOrFail();
        $this->actingAs($client)->post($url.'/revisions', ['submission' => $first->id, 'changes' => 'Please correct the Arabic layout and the totals.'])->assertSessionHasNoErrors();
        $this->assertSame('revision_requested', $contract->fresh()->status);
        $this->actingAs($freelancer)->post($url.'/amendments', ['reason' => 'One more round would help here.', 'extra_rounds' => 1])->assertSessionHasNoErrors();
        $this->actingAs($client)->patch($url.'/amendments', ['amendment' => ContractAmendment::query()->latest('id')->value('id'), 'action' => 'accept'])->assertSessionHasNoErrors();
        $this->actingAs($freelancer)->post($url.'/cancellation', ['reason' => 'I would like to stop this work.'])->assertSessionHasNoErrors();
        $this->actingAs($client)->patch($url.'/cancellation', ['action' => 'decline'])->assertSessionHasNoErrors();
        $this->actingAs($freelancer)->post($url.'/deliveries', $this->delivery())->assertSessionHasNoErrors();
        $this->actingAs($client)->post($url.'/complete', ['submission' => ContractSubmission::query()->latest('id')->value('id')])->assertSessionHasNoErrors();
        $this->put($url.'/review', ['rating' => 5, 'body' => 'Clear communication and careful work throughout.'])->assertSessionHasNoErrors();
        $this->assertSame('completed', $contract->fresh()->status);

        // The client still reads their own list, brief and the hired proposal; nothing on them can be changed.
        $this->get('/my-projects')->assertOk()->assertInertia(fn (Assert $page) => $page->where('readOnly', true)->has('projects.data', 1));
        $this->get('/jobs/'.$contract->project_id)->assertOk()->assertInertia(fn (Assert $page) => $page->where('application.owner', true));
        $this->get('/proposals/'.$contract->proposal_id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('author', false)->where('canOffer', false)->where('canStartConversation', false)->where('canReview', false));
        $this->get('/my-projects/'.$contract->project_id.'/proposals')->assertForbidden();
        $this->post('/my-projects')->assertForbidden();
        $draft = new Project;
        $draft->forceFill(['user_id' => $client->id, 'application_closes_at' => now()->addDays(7)])->save();
        $this->get('/my-projects/'.$draft->id.'/edit')->assertForbidden();
        $this->delete('/my-projects/'.$draft->id)->assertForbidden();
        $this->get('/jobs/'.$draft->id)->assertNotFound();
        // The brief is gone from public pages for everyone else.
        $this->actingAs($freelancer)->get('/jobs/'.$contract->project_id)->assertNotFound();
        $this->actingAs($client);

        // An unfunded contract can be cancelled by the suspended client, but its brief cannot be reposted.
        [$second, , $unfunded] = $this->contract(funded: false);
        $second->forceFill(['status' => 'suspended'])->save();
        $this->actingAs($second)->post('/contracts/'.$unfunded->id.'/payments', ['provider' => 'simulator', 'client_token' => (string) Str::uuid()])->assertSessionHasErrors('payment');
        $this->post('/contracts/'.$unfunded->id.'/cancellation', ['reason' => 'I no longer need this work.'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $unfunded->fresh()->status);
        $this->post('/contracts/'.$unfunded->id.'/repost')->assertForbidden();
    }
}

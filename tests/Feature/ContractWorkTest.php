<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Contract;
use App\Models\ContractRevisionRequest;
use App\Models\ContractSubmission;
use App\Models\ContractSubmissionFile;
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

class ContractWorkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** @return array{User, User, Contract} */
    private function contract(int $rounds = 1, bool $funded = true): array
    {
        $client = User::factory()->create(['onboarding_completed_at' => now(), 'name' => 'Clara Client']);
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

    private function deliver(User $freelancer, Contract $contract): ContractSubmission
    {
        $this->actingAs($freelancer)->post('/contracts/'.$contract->id.'/deliveries', $this->delivery())->assertSessionHasNoErrors();

        return ContractSubmission::query()->latest('id')->firstOrFail();
    }

    public function test_only_the_freelancer_delivers_and_only_after_funding(): void
    {
        [$client, $freelancer, $contract] = $this->contract(funded: false);
        $this->actingAs($freelancer)->post('/contracts/'.$contract->id.'/deliveries', $this->delivery())->assertSessionHasErrors('delivery');
        $contract->forceFill(['status' => 'active', 'funded_at' => now(), 'delivery_due_at' => now()->addDays(14)])->save();
        $this->actingAs($client)->post('/contracts/'.$contract->id.'/deliveries', $this->delivery())->assertForbidden();
        $stranger = User::factory()->create(['onboarding_completed_at' => now(), 'is_admin' => true]);
        $this->actingAs($stranger)->post('/contracts/'.$contract->id.'/deliveries', $this->delivery())->assertNotFound();
        // Q49: a delivery must be declared complete, and links must be web addresses.
        $this->actingAs($freelancer)->post('/contracts/'.$contract->id.'/deliveries', $this->delivery(['complete' => false]))->assertSessionHasErrors('complete');
        $this->post('/contracts/'.$contract->id.'/deliveries', $this->delivery(['links' => ['javascript:alert(1)']]))->assertSessionHasErrors('links.0');
        $this->assertSame(0, ContractSubmission::query()->count());
        $this->assertSame(0, $client->notifications()->get()->where('data.kind', 'delivery_submitted')->count());
    }

    public function test_a_delivery_is_recorded_once_with_private_files(): void
    {
        [$client, $freelancer, $contract] = $this->contract();
        $payload = $this->delivery(['links' => ['https://example.test/demo'], 'files' => [UploadedFile::fake()->create('handover.pdf', 120, 'application/pdf')]]);
        $this->actingAs($freelancer)->post('/contracts/'.$contract->id.'/deliveries', $payload)->assertRedirect('/contracts/'.$contract->id);
        // The same token again (double click, retry) records nothing new.
        $this->post('/contracts/'.$contract->id.'/deliveries', $payload)->assertSessionHasNoErrors();
        $this->assertSame(1, ContractSubmission::query()->count());
        $this->assertSame(1, ContractSubmissionFile::query()->count());
        $this->assertSame('submitted', $contract->fresh()->status);
        $this->assertSame(1, $client->notifications()->get()->where('data.kind', 'delivery_submitted')->count());
        // A second delivery cannot be stacked on one awaiting review.
        $this->post('/contracts/'.$contract->id.'/deliveries', $this->delivery())->assertSessionHasErrors('delivery');

        $file = ContractSubmissionFile::query()->firstOrFail();
        Storage::disk('local')->assertExists($file->path);
        $this->get('/contracts/'.$contract->id.'/files/'.$file->id)->assertOk()->assertDownload('handover.pdf');
        $this->actingAs($client)->get('/contracts/'.$contract->id.'/files/'.$file->id)->assertOk();
        $this->get('/contracts/'.$contract->id)->assertInertia(fn (Assert $page) => $page->where('contract.status', 'submitted')
            ->where('submissions.0.number', 1)->where('submissions.0.files.0.name', 'handover.pdf')
            ->missing('submissions.0.files.0.path')->missing('submissions.0.client_token')->where('activity.0.kind', 'delivered'));
        // Not a participant, and not through another contract's address.
        [, $other, $second] = $this->contract();
        $this->actingAs($other)->get('/contracts/'.$contract->id.'/files/'.$file->id)->assertNotFound();
        $this->get('/contracts/'.$second->id.'/files/'.$file->id)->assertNotFound();
    }

    public function test_one_revision_request_consumes_one_round_and_the_allowance_is_enforced(): void
    {
        [$client, $freelancer, $contract] = $this->contract(rounds: 1);
        $first = $this->deliver($freelancer, $contract);
        $payload = ['submission' => $first->id, 'changes' => 'Please correct the Arabic layout and the totals.'];
        $this->actingAs($freelancer)->post('/contracts/'.$contract->id.'/revisions', $payload)->assertForbidden();
        $this->actingAs($client)->post('/contracts/'.$contract->id.'/revisions', $payload)->assertSessionHasNoErrors();
        // Q48: a repeated request for the same delivery uses no second round.
        $this->post('/contracts/'.$contract->id.'/revisions', $payload)->assertSessionHasNoErrors();
        $this->assertSame(1, ContractRevisionRequest::query()->count());
        $this->assertSame(['revision_requested', 1], [$contract->fresh()->status, $contract->fresh()->revisions_used]);
        $this->assertSame(1, $freelancer->notifications()->get()->where('data.kind', 'revision_requested')->count());
        // Approval is not possible while a revision is outstanding.
        $this->post('/contracts/'.$contract->id.'/complete', ['submission' => $first->id])->assertSessionHasErrors('delivery');

        // The redelivery uses no round; with the allowance spent a further request is refused.
        $second = $this->deliver($freelancer, $contract);
        $this->assertSame(['submitted', 1, 2], [$contract->fresh()->status, $contract->fresh()->revisions_used, $second->number]);
        $this->actingAs($client)->post('/contracts/'.$contract->id.'/revisions', ['submission' => $second->id, 'changes' => 'One more round of changes, please.'])->assertSessionHasErrors('revision');
        $this->assertSame('submitted', $contract->fresh()->status);
    }

    public function test_only_the_client_completes_and_only_the_latest_delivery(): void
    {
        [$client, $freelancer, $contract] = $this->contract(rounds: 2);
        $first = $this->deliver($freelancer, $contract);
        $this->actingAs($client)->post('/contracts/'.$contract->id.'/revisions', ['submission' => $first->id, 'changes' => 'Please correct the Arabic layout and the totals.']);
        $second = $this->deliver($freelancer, $contract);
        $this->actingAs($freelancer)->post('/contracts/'.$contract->id.'/complete', ['submission' => $second->id])->assertForbidden();
        // A page still showing the first delivery cannot approve or request against it.
        $this->actingAs($client)->post('/contracts/'.$contract->id.'/complete', ['submission' => $first->id])->assertSessionHasErrors('delivery');
        $this->assertSame('submitted', $contract->fresh()->status);
        $this->post('/contracts/'.$contract->id.'/complete', ['submission' => $second->id])->assertSessionHasNoErrors();
        $this->post('/contracts/'.$contract->id.'/complete', ['submission' => $second->id])->assertSessionHasNoErrors();
        $this->assertSame('completed', $contract->fresh()->status);
        $this->assertNotNull($contract->fresh()->completed_at);
        $this->assertSame(1, $freelancer->notifications()->get()->where('data.kind', 'contract_completed')->count());
        // Nothing further is accepted on a completed contract.
        $this->actingAs($freelancer)->post('/contracts/'.$contract->id.'/deliveries', $this->delivery())->assertSessionHasErrors('delivery');
        $this->actingAs($client)->post('/contracts/'.$contract->id.'/revisions', ['submission' => $second->id, 'changes' => 'A late request after completion.'])->assertSessionHasErrors('delivery');
    }

    public function test_reviews_stay_hidden_until_both_exist_then_lock(): void
    {
        [$client, $freelancer, $contract] = $this->contract();
        $review = ['rating' => 5, 'body' => 'Clear communication and careful work throughout.'];
        $this->actingAs($client)->put('/contracts/'.$contract->id.'/review', $review)->assertSessionHasErrors('review');
        $submission = $this->deliver($freelancer, $contract);
        $this->actingAs($client)->post('/contracts/'.$contract->id.'/complete', ['submission' => $submission->id]);
        $this->put('/contracts/'.$contract->id.'/review', ['rating' => 6, 'body' => 'short'])->assertSessionHasErrors(['rating', 'body']);
        $this->put('/contracts/'.$contract->id.'/review', $review)->assertSessionHasNoErrors();
        // Hidden: the author may edit; the counterpart learns only that one exists.
        $this->put('/contracts/'.$contract->id.'/review', [...$review, 'rating' => 4])->assertSessionHasNoErrors();
        // The replaced version is kept privately; saving the same review again adds nothing.
        $this->put('/contracts/'.$contract->id.'/review', [...$review, 'rating' => 4])->assertSessionHasNoErrors();
        $this->assertSame([5], DB::table('contract_review_revisions')->pluck('rating')->all());
        $this->get('/contracts/'.$contract->id)->assertInertia(fn (Assert $page) => $page->where('reviews.mine', ['rating' => 4, 'body' => $review['body']]));
        $this->actingAs($freelancer)->get('/contracts/'.$contract->id)->assertInertia(fn (Assert $page) => $page
            ->where('reviews.published', false)->where('reviews.theirs', null)->where('reviews.theirs_submitted', true)->where('reviews.mine', null));
        $profile = $freelancer->profile()->create(['headline' => 'Laravel developer', 'bio' => 'I build bilingual web applications.']);
        $profile->syncSkillTags(['Laravel']);
        $profile->forceFill(['published_at' => now()])->save();
        $this->get('/freelancers/'.$profile->id)->assertInertia(fn (Assert $page) => $page->has('reviews', 0));
        $this->assertSame(1, $freelancer->notifications()->get()->where('data.kind', 'review_received')->count());

        $this->put('/contracts/'.$contract->id.'/review', ['rating' => 5, 'body' => 'A precise brief and quick answers.'])->assertSessionHasNoErrors();
        // Both exist: published and locked for both authors.
        $this->put('/contracts/'.$contract->id.'/review', ['rating' => 1, 'body' => 'Changing my mind after reading theirs.'])->assertSessionHasErrors('review');
        $this->actingAs($client)->put('/contracts/'.$contract->id.'/review', [...$review, 'rating' => 1])->assertSessionHasErrors('review');
        $this->get('/contracts/'.$contract->id)->assertInertia(fn (Assert $page) => $page
            ->where('reviews.published', true)->where('reviews.mine.rating', 4)->where('reviews.theirs.rating', 5));
        $this->get('/freelancers/'.$profile->id)->assertInertia(fn (Assert $page) => $page->has('reviews', 1)
            ->where('reviews.0.rating', 4)->where('reviews.0.author', 'Clara')->missing('reviews.0.author_id'));
        $stranger = User::factory()->create(['onboarding_completed_at' => now()]);
        $this->actingAs($stranger)->put('/contracts/'.$contract->id.'/review', $review)->assertNotFound();
    }

    public function test_a_lone_review_publishes_at_the_fourteen_day_boundary(): void
    {
        [$client, $freelancer, $contract] = $this->contract();
        $submission = $this->deliver($freelancer, $contract);
        $this->actingAs($client)->post('/contracts/'.$contract->id.'/complete', ['submission' => $submission->id]);
        $this->put('/contracts/'.$contract->id.'/review', ['rating' => 5, 'body' => 'Clear communication and careful work throughout.']);
        $this->travel(13)->days();
        $this->actingAs($freelancer)->get('/contracts/'.$contract->id)->assertInertia(fn (Assert $page) => $page->where('reviews.published', false)->where('reviews.theirs', null));
        $this->travel(2)->days();
        $this->get('/contracts/'.$contract->id)->assertInertia(fn (Assert $page) => $page->where('reviews.published', true)->where('reviews.theirs.rating', 5));
        // Published reviews lock; a first review after the boundary is still accepted.
        $this->actingAs($client)->put('/contracts/'.$contract->id.'/review', ['rating' => 2, 'body' => 'An edit after the publication boundary.'])->assertSessionHasErrors('review');
        $this->actingAs($freelancer)->put('/contracts/'.$contract->id.'/review', ['rating' => 4, 'body' => 'A late first review is published at once.'])->assertSessionHasNoErrors();
    }
}

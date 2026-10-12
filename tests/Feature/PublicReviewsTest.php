<?php

namespace Tests\Feature;

use App\Actions\Contracts\ContractWork;
use App\Models\Category;
use App\Models\Contract;
use App\Models\Conversation;
use App\Models\Offer;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicReviewsTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name): User
    {
        return User::factory()->create(['name' => $name, 'onboarding_completed_at' => now()]);
    }

    private function project(User $client, string $title, array $attributes = []): Project
    {
        $project = new Project;
        $project->forceFill(['user_id' => $client->id, 'category_id' => Category::query()->firstOrCreate(['categoryname' => 'Development'])->id,
            'title' => $title, 'description' => 'A complete project brief.', 'budget_min' => 100, 'budget_max' => 500,
            'status' => 'published', 'published_at' => now()->subDays(60), 'application_closes_at' => now()->addDays(7), ...$attributes])->save();

        return $project;
    }

    /** A contract as accepting an offer leaves it, completed the given number of days ago (null: still active). */
    private function contract(User $client, User $freelancer, string $title, ?int $completedDaysAgo = 0): Contract
    {
        $project = $this->project($client, $title, ['status' => 'hired', 'application_closes_at' => now()->subDays(50)]);
        $proposal = new Proposal;
        $proposal->forceFill(['project_id' => $project->id, 'user_id' => $freelancer->id, 'status' => 'submitted', 'version' => 1, 'submitted_at' => now()->subDays(55)])->save();
        $offer = new Offer;
        $offer->forceFill(['project_id' => $project->id, 'proposal_id' => $proposal->id, 'client_id' => $client->id, 'freelancer_id' => $freelancer->id,
            'client_token' => (string) Str::uuid(), 'terms' => ['amount' => '300.00'], 'status' => 'accepted', 'expires_at' => now()->subDays(45), 'closed_at' => now()->subDays(48)])->save();
        $conversation = new Conversation;
        $conversation->forceFill(['proposal_id' => $proposal->id, 'client_id' => $client->id, 'freelancer_id' => $freelancer->id])->save();
        $contract = new Contract;
        $contract->forceFill(['project_id' => $project->id, 'proposal_id' => $proposal->id, 'offer_id' => $offer->id, 'client_id' => $client->id,
            'freelancer_id' => $freelancer->id, 'conversation_id' => $conversation->id,
            'agreement' => ['amount' => '300.00', 'revision_rounds' => 1, 'project_title' => $title, 'client_name' => $client->name, 'freelancer_name' => $freelancer->name],
            'status' => $completedDaysAgo === null ? 'active' : 'completed', 'completed_at' => $completedDaysAgo === null ? null : now()->subDays($completedDaysAgo)])->save();

        return $contract;
    }

    /** Both ratings publish a contract's reviews at once; one alone waits for the fourteen-day boundary. */
    private function review(Contract $contract, ?int $ofFreelancer, ?int $ofClient): void
    {
        if ($ofFreelancer !== null) {
            ContractWork::review($contract, $contract->client_id, $ofFreelancer, 'The work arrived on time and matched the brief.');
        }
        if ($ofClient !== null) {
            ContractWork::review($contract, $contract->freelancer_id, $ofClient, 'A precise brief, quick answers and fair feedback.');
        }
    }

    private function publicProfile(User $freelancer): Profile
    {
        $profile = $freelancer->profile()->create(['headline' => 'Laravel developer', 'bio' => 'I build bilingual web applications.']);
        $profile->syncSkillTags(['Laravel']);
        $profile->forceFill(['published_at' => now()->subDay()])->save();

        return $profile;
    }

    public function test_a_project_page_tells_everyone_the_clients_completed_projects_and_published_reviews(): void
    {
        $client = $this->member('Clara Client');
        $farid = $this->member('Farid Freelancer');
        $bea = $this->member('Bea Builder');
        $brief = $this->project($client, 'Open brief');
        $this->get('/jobs/'.$brief->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('client.completed', 0)->where('client.rating', ['count' => 0, 'average' => null])->where('client.reviews', []));

        $first = $this->contract($client, $farid, 'First job');
        $this->review($first, 3, 5);
        // Completed long enough ago that one review alone is published.
        $this->review($this->contract($client, $bea, 'Second job', 20), null, 4);
        // Hidden: a lone review inside the fourteen days. The completed contract still counts.
        $recent = $this->contract($client, $bea, 'Third job', 13);
        $this->review($recent, null, 1);
        $this->review($this->contract($client, $farid, 'Unfinished job', null), null, null);
        // The same member reviewed as a freelancer on someone else's project: never part of their client record.
        $this->review($this->contract($this->member('Omar Owner'), $client, 'Work for Omar'), 1, 5);

        $expect = fn (Assert $page) => $page->where('client.name', 'Clara')->where('client.completed', 3)
            ->where('client.rating', ['count' => 2, 'average' => 4.5])->has('client.reviews', 2)
            ->where('client.reviews.0.author', 'Bea')->where('client.reviews.0.rating', 4)->where('client.reviews.0.project_title', 'Second job')
            ->where('client.reviews.1.author', 'Farid')->where('client.reviews.1.rating', 5)->where('client.reviews.1.body', 'A precise brief, quick answers and fair feedback.')
            ->missing('client.reviews.0.author_id')->missing('client.reviews.0.subject_id')->missing('client.reviews.0.contract_id')->missing('client.reviews.0.contract')
            ->missing('client.email')->missing('client.id');
        $this->get('/jobs/'.$brief->id)->assertOk()->assertInertia($expect);
        $this->actingAs($farid)->get('/jobs/'.$brief->id)->assertOk()->assertInertia($expect);

        // A project hidden by moderation no longer lends its title to a review; the review stays.
        Project::query()->whereKey($first->project_id)->update(['moderated_at' => now()]);
        $this->get('/jobs/'.$brief->id)->assertInertia(fn (Assert $page) => $page->has('client.reviews', 2)
            ->where('client.reviews.1.author', 'Farid')->where('client.reviews.1.project_title', null)->where('client.reviews.0.project_title', 'Second job'));

        // The query and the per-contract rule agree on the day a lone review publishes.
        $this->assertFalse(ContractWork::reviewsPublished($recent));
        $this->travel(2)->days();
        $this->assertTrue(ContractWork::reviewsPublished($recent->refresh()));
        $this->get('/jobs/'.$brief->id)->assertInertia(fn (Assert $page) => $page->where('client.completed', 3)
            ->where('client.rating', ['count' => 3, 'average' => 3.3])->where('client.reviews.0.rating', 1)->where('client.reviews.0.author', 'Bea'));
    }

    public function test_freelancer_pages_and_cards_carry_the_rating_for_their_own_work_only(): void
    {
        $freelancer = $this->member('Farid Freelancer');
        $profile = $this->publicProfile($freelancer);
        $unrated = $this->publicProfile($this->member('Nour New'));
        $client = $this->member('Clara Client');
        foreach ([5, 4, 5] as $index => $rating) {
            $this->review($this->contract($client, $freelancer, 'Job '.($index + 1)), $rating, 5);
        }
        // Completed, with the client's review still hidden.
        $this->review($this->contract($client, $freelancer, 'Job 4', 1), 1, null);
        // The same member reviewed as a client: never part of the rating for their work.
        $this->review($this->contract($freelancer, $this->member('Wael Worker'), 'Hiring Wael'), 5, 1);
        $rating = ['count' => 3, 'average' => 4.7];

        $this->get('/freelancers/'.$profile->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('record', ['completed' => 4, 'rating' => $rating])->has('reviews', 3)
            ->where('reviews.0.rating', 5)->where('reviews.0.author', 'Clara')->where('reviews.0.project_title', 'Job 3')->where('reviews.1.rating', 4)
            ->missing('reviews.0.author_id')->missing('reviews.0.contract')->missing('freelancer.rating'));
        $this->get('/freelancers/'.$unrated->id)->assertInertia(fn (Assert $page) => $page
            ->where('record', ['completed' => 0, 'rating' => ['count' => 0, 'average' => null]])->where('reviews', []));
        $this->get('/freelancers')->assertInertia(fn (Assert $page) => $page->has('freelancers.data', 2)
            ->where('freelancers.data', fn ($cards) => collect($cards)->pluck('rating', 'id')->all() == [$profile->id => $rating, $unrated->id => ['count' => 0, 'average' => null]]));
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('freelancers', fn ($cards) => collect($cards)->firstWhere('id', $profile->id)['rating'] === $rating
                && collect($cards)->firstWhere('id', $unrated->id)['rating'] === ['count' => 0, 'average' => null]));
        DB::table('saved_profiles')->insert(['user_id' => $client->id, 'profile_id' => $profile->id, 'created_at' => now()]);
        $this->actingAs($client)->get('/saved/freelancers')->assertInertia(fn (Assert $page) => $page->where('saves.data.0.freelancer.rating', $rating));

        // More published reviews than the profile lists: the count stays exact.
        foreach (range(5, 12) as $number) {
            $this->review($this->contract($client, $freelancer, 'Job '.$number), 5, 5);
        }
        $this->get('/freelancers/'.$profile->id)->assertInertia(fn (Assert $page) => $page->has('reviews', 10)
            ->where('record.completed', 12)->where('record.rating', ['count' => 11, 'average' => 4.9])->where('reviews.0.project_title', 'Job 12'));
        // A private profile keeps its published reviews and its rating behind it; the public page is gone.
        $profile->forceFill(['published_at' => null])->save();
        $this->get('/freelancers/'.$profile->id)->assertNotFound();
    }
}

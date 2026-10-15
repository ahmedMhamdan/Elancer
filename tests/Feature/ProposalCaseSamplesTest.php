<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Models\Category;
use App\Models\Contract;
use App\Models\Offer;
use App\Models\PortfolioApproval;
use App\Models\PortfolioCase;
use App\Models\PortfolioImage;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\ProposalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Plan 031 (Q37, Q55): case studies sent with a proposal, and what the client keeps of them. */
class ProposalCaseSamplesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('uploads');
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function member(string $name = 'Member'): User
    {
        return User::factory()->create(['onboarding_completed_at' => now(), 'name' => $name]);
    }

    private function freelancer(): User
    {
        $user = $this->member('Fadi Freelancer');
        $user->forceFill(['workspace_role' => WorkspaceRole::Freelancer])->save();
        $profile = $user->profile()->create(['headline' => 'Laravel developer', 'bio' => 'I build accessible applications.']);
        $profile->syncSkillTags(['Laravel']);
        $profile->forceFill(['published_at' => now()])->save();

        return $user;
    }

    private function project(User $owner, bool $open = true): Project
    {
        $project = new Project;
        $project->forceFill(['user_id' => $owner->id, 'category_id' => Category::query()->firstOrCreate(['categoryname' => 'Development'])->id,
            'title' => 'Build a website '.Str::random(4), 'description' => str_repeat('A clear specification for the project. ', 3), 'budget_min' => 100, 'budget_max' => 500,
            'status' => 'published', 'published_at' => now()->subHour(), 'application_closes_at' => $open ? now()->addDays(7) : now()->subMinute()])->save();

        return $project;
    }

    /** @return array<string, mixed> */
    private function content(string $title, array $images = []): array
    {
        return ['title' => $title, 'summary' => 'A short summary of the work that was done.', 'body' => str_repeat('What was built and how it helped. ', 3),
            'skills' => ['Laravel'], 'links' => [['label' => 'Live site', 'url' => 'https://example.org/site']], ...($images ? ['images' => $images] : [])];
    }

    private function case(User $owner, ?string $public, ?int $contract = null): PortfolioCase
    {
        $case = new PortfolioCase;
        $case->forceFill(['user_id' => $owner->id, 'contract_id' => $contract, 'content' => $this->content($public ?? 'Unpublished case'),
            'public_content' => $public === null ? null : $this->content($public), 'published_at' => $public === null ? null : now()])->save();

        return $case;
    }

    private function image(PortfolioCase $case): PortfolioImage
    {
        $image = new PortfolioImage;
        $image->forceFill(['portfolio_case_id' => $case->id, 'path' => 'portfolio-images/'.$case->id.'/'.Str::uuid().'.jpg', 'width' => 10, 'height' => 10])->save();
        Storage::disk('uploads')->put($image->path, 'jpeg bytes');

        return $image;
    }

    /** @return array<string, mixed> */
    private function proposal(string $action, int $version, array $cases): array
    {
        return ['action' => $action, 'version' => $version, 'price' => '650.50', 'duration_days' => 14,
            'message' => 'I will build and test your accessible Laravel application.', 'answers' => [], 'samples' => [], 'cases' => $cases];
    }

    public function test_the_client_keeps_the_copy_that_was_sent(): void
    {
        $freelancer = $this->freelancer();
        $client = $this->member('Clara Client');
        $project = $this->project($client);
        $published = $this->case($freelancer, 'Shop for a bakery');
        $image = $this->image($published);
        $published->forceFill(['content' => $this->content('Shop for a bakery', [['id' => $image->id, 'alt' => 'The home page']]),
            'public_content' => $this->content('Shop for a bakery', [['id' => $image->id, 'alt' => 'The home page']])])->save();
        $draft = $this->case($freelancer, null);
        $someoneElses = $this->case($this->freelancer(), 'Not mine');
        $apply = '/jobs/'.$project->id.'/proposal';

        $this->actingAs($freelancer)->get('/jobs/'.$project->id.'/apply')->assertInertia(fn (Assert $page) => $page
            ->where('caseLimit', 3)->has('caseOptions', 2)
            ->where('caseOptions.0.id', $draft->id)->where('caseOptions.0.shareable', false)
            ->where('caseOptions.1.id', $published->id)->where('caseOptions.1.shareable', true));
        $this->putJson($apply, $this->proposal('save', 0, [$someoneElses->id]))->assertUnprocessable()->assertJsonValidationErrors('cases.0');
        $this->putJson($apply, $this->proposal('save', 0, [$published->id, $draft->id]))->assertOk()->assertJsonPath('proposal.draft.cases', [$published->id, $draft->id]);
        // A case that is not public cannot be sent.
        $this->putJson($apply, $this->proposal('submit', 1, [$published->id, $draft->id]))->assertUnprocessable()->assertJsonValidationErrors('cases');
        $this->assertSame(0, ProposalCase::query()->count());
        // A save that does not name the cases keeps the ones chosen.
        $without = $this->proposal('save', 1, []);
        unset($without['cases']);
        $this->putJson($apply, $without)->assertOk()->assertJsonPath('proposal.draft.cases', [$published->id, $draft->id]);
        $this->putJson($apply, $this->proposal('submit', 2, [$published->id]))->assertOk();
        $proposal = Proposal::query()->firstOrFail();
        $copy = ProposalCase::query()->sole();
        $this->assertSame([[$published->id], [$copy->id]], [$proposal->content['cases'], $proposal->content['shared']]);

        $imageUrl = '/proposals/'.$proposal->id.'/cases/'.$copy->id.'/images/'.$image->id;
        $this->actingAs($client)->get('/proposals/'.$proposal->id)->assertInertia(fn (Assert $page) => $page
            ->where('proposal.content.case_samples.0.title', 'Shop for a bakery')->where('proposal.content.case_samples.0.withheld', false)
            ->where('proposal.content.case_samples.0.case_id', null)->where('proposal.content.case_samples.0.elancer_work', false)
            ->where('proposal.content.case_samples.0.images.0.alt', 'The home page')
            ->where('proposal.events.0.content.case_samples.0.id', $copy->id));
        $this->get($imageUrl)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($this->member())->get($imageUrl)->assertNotFound();
        $this->actingAs($freelancer)->get($imageUrl)->assertOk();

        // The freelancer changes the public version, drops the image and hides the case: the client still has what was sent.
        $this->actingAs($freelancer)->put('/my-portfolio/'.$published->id, $this->content('Shop for a bakery, second edition'))->assertSessionHasNoErrors();
        $this->patch('/my-portfolio/'.$published->id, ['action' => 'publish'])->assertSessionHasNoErrors();
        $this->patch('/my-portfolio/'.$published->id, ['action' => 'hide'])->assertSessionHasNoErrors();
        $this->assertNotNull(PortfolioImage::query()->find($image->id), 'An image a proposal sent stays stored.');
        $this->actingAs($client)->get('/proposals/'.$proposal->id)->assertInertia(fn (Assert $page) => $page
            ->where('proposal.content.case_samples.0.title', 'Shop for a bakery'));
        $this->get($imageUrl)->assertOk();

        // A hidden case is not sent again; shown again, its new public version is a new copy, and the history keeps the first.
        $this->actingAs($freelancer)->putJson($apply, $this->proposal('submit', 3, [$published->id]))->assertUnprocessable()->assertJsonValidationErrors('cases');
        $this->patch('/my-portfolio/'.$published->id, ['action' => 'show'])->assertSessionHasNoErrors();
        $this->putJson($apply, $this->proposal('submit', 3, [$published->id]))->assertOk();
        $this->assertSame(2, ProposalCase::query()->count());
        $this->putJson($apply, $this->proposal('submit', 4, [$published->id]))->assertOk();
        $this->assertSame(2, ProposalCase::query()->count(), 'An unchanged case keeps its copy.');
        $this->actingAs($client)->get('/proposals/'.$proposal->id)->assertInertia(fn (Assert $page) => $page
            ->where('proposal.content.case_samples.0.title', 'Shop for a bakery, second edition')
            ->where('proposal.events.2.content.case_samples.0.title', 'Shop for a bakery'));

        // A case sent on a proposal is history: it cannot be deleted.
        $this->actingAs($freelancer)->delete('/my-portfolio/'.$published->id)->assertSessionHasErrors('case');
        $this->actingAs($freelancer)->get('/my-portfolio')->assertInertia(fn (Assert $page) => $page->where('cases.1.deletable', false));

        // Q68: moderation hides the copies from the client; the freelancer sees them, marked.
        $published->forceFill(['moderated_at' => now()])->save();
        $this->actingAs($client)->get('/proposals/'.$proposal->id)->assertInertia(fn (Assert $page) => $page
            ->where('proposal.content.case_samples.0', ['id' => ProposalCase::query()->max('id'), 'withheld' => true]));
        $this->get($imageUrl)->assertNotFound();
        $this->actingAs($freelancer)->get('/proposals/'.$proposal->id)->assertInertia(fn (Assert $page) => $page
            ->where('proposal.content.case_samples.0.withheld', true)->where('proposal.content.case_samples.0.case_id', $published->id));
    }

    public function test_client_owned_work_follows_its_clients_approval(): void
    {
        $freelancer = $this->freelancer();
        $owner = $this->member('Olga Owner');
        $earlier = $this->project($owner, open: false);
        $proposal = new Proposal;
        $proposal->forceFill(['project_id' => $earlier->id, 'user_id' => $freelancer->id, 'status' => 'submitted', 'version' => 1,
            'content' => ['price' => '300.00', 'duration_days' => 7], 'submitted_at' => now()->subHours(2)])->save();
        $this->actingAs($owner)->post('/proposals/'.$proposal->id.'/offer', ['scope' => 'Build a bilingual application with the agreed account and project workflows.',
            'deliverables' => ['Application source code'], 'amount' => '750.25', 'duration_days' => 14, 'revision_rounds' => 1,
            'proposal_version' => 1, 'client_token' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->actingAs($freelancer)->patch('/offers/'.Offer::query()->latest('id')->value('id'), ['action' => 'accept', 'version' => 1])->assertSessionHasNoErrors();
        $contract = Contract::query()->sole();
        $contract->forceFill(['status' => 'completed', 'funded_at' => now()->subDays(3), 'completed_at' => now()->subDay()])->save();
        $case = $this->case($freelancer, 'Application for Olga', $contract->id);
        (new PortfolioApproval)->forceFill(['portfolio_case_id' => $case->id, 'content' => $case->public_content, 'status' => 'approved', 'decided_at' => now()])->save();

        $client = $this->member('Clara Client');
        $project = $this->project($client);
        $this->actingAs($freelancer)->putJson('/jobs/'.$project->id.'/proposal', $this->proposal('submit', 0, [$case->id]))->assertOk();
        $sent = Proposal::query()->where('project_id', $project->id)->sole();
        $copy = ProposalCase::query()->sole();
        $read = fn () => $this->actingAs($client)->get('/proposals/'.$sent->id);
        $read()->assertInertia(fn (Assert $page) => $page->where('proposal.content.case_samples.0.title', 'Application for Olga')
            ->where('proposal.content.case_samples.0.elancer_work', true));

        // Q55: the originating client withdraws permission; every copy disappears for the proposal's client.
        $this->actingAs($owner)->patch('/contracts/'.$contract->id.'/portfolio', ['action' => 'revoke'])->assertSessionHasNoErrors();
        $read()->assertInertia(fn (Assert $page) => $page->where('proposal.content.case_samples.0', ['id' => $copy->id, 'withheld' => true]));
        $this->actingAs($freelancer)->get('/proposals/'.$sent->id)->assertInertia(fn (Assert $page) => $page
            ->where('proposal.content.case_samples.0.withheld', true));

        // Only a new approval of the same content shows it again.
        $this->actingAs($freelancer)->patch('/my-portfolio/'.$case->id, ['action' => 'request'])->assertSessionHasNoErrors();
        $read()->assertInertia(fn (Assert $page) => $page->where('proposal.content.case_samples.0.withheld', true));
        $this->actingAs($owner)->patch('/contracts/'.$contract->id.'/portfolio', ['action' => 'approve', 'approval' => PortfolioApproval::query()->where('status', 'pending')->value('id')])->assertSessionHasNoErrors();
        $read()->assertInertia(fn (Assert $page) => $page->where('proposal.content.case_samples.0.title', 'Application for Olga')
            ->where('proposal.content.case_samples.0.withheld', false));
    }
}

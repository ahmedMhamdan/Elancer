<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\Category;
use App\Models\Contract;
use App\Models\Offer;
use App\Models\PortfolioApproval;
use App\Models\PortfolioCase;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PortfolioTest extends TestCase
{
    use RefreshDatabase;

    private function freelancer(): User
    {
        $freelancer = User::factory()->create(['onboarding_completed_at' => now(), 'name' => 'Farah Freelancer']);
        $profile = $freelancer->profile()->create(['headline' => 'Laravel developer', 'bio' => 'I build bilingual web applications.']);
        $profile->syncSkillTags(['Laravel']);
        $profile->forceFill(['published_at' => now()])->save();

        return $freelancer;
    }

    /** @return array{User, Contract} */
    private function contract(User $freelancer, string $status = 'completed'): array
    {
        $client = User::factory()->create(['onboarding_completed_at' => now(), 'name' => 'Clara Client']);
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
        $contract->forceFill(['status' => $status, 'funded_at' => now(), 'completed_at' => $status === 'completed' ? now() : null])->save();

        return [$client, $contract];
    }

    /** @return array<string, mixed> */
    private function content(array $extra = []): array
    {
        return ['title' => 'Bilingual booking platform', 'summary' => 'A booking platform in English and Arabic for a clinic group.',
            'body' => 'I designed the data model, built the booking flow and delivered a right-to-left interface with tests.',
            'skills' => ['Laravel'], 'links' => [['label' => 'Source code', 'url' => 'https://github.com/example/booking']], ...$extra];
    }

    public function test_a_manual_case_is_public_only_while_published_shown_and_its_profile_is_visible(): void
    {
        $freelancer = $this->freelancer();
        $this->actingAs($freelancer)->post('/my-portfolio', $this->content(['links' => [['label' => 'Plain', 'url' => 'http://example.com']]]))->assertSessionHasErrors('links.0.url');
        $this->post('/my-portfolio', $this->content(['skills' => ['Not a catalog skill']]))->assertSessionHasErrors('skills.0');
        $this->post('/my-portfolio', $this->content())->assertSessionHasNoErrors();
        $case = PortfolioCase::query()->firstOrFail();
        $public = '/portfolio/'.$case->id;
        $profile = '/freelancers/'.$freelancer->profile->id;
        // A saved draft is private.
        $this->get($public)->assertNotFound();
        $this->get($profile)->assertInertia(fn (Assert $page) => $page->has('cases', 0));

        $this->patch('/my-portfolio/'.$case->id, ['action' => 'publish'])->assertSessionHasNoErrors();
        $this->get($public)->assertInertia(fn (Assert $page) => $page->where('case.content.title', 'Bilingual booking platform')->where('case.elancer_work', false)->where('freelancer.name', 'Farah Freelancer'));
        $this->get($profile)->assertInertia(fn (Assert $page) => $page->has('cases', 1));

        // Saving an edit never changes the public version until it is published again.
        $this->put('/my-portfolio/'.$case->id, $this->content(['title' => 'A renamed case study']))->assertSessionHasNoErrors();
        $this->get($public)->assertInertia(fn (Assert $page) => $page->where('case.content.title', 'Bilingual booking platform'));
        $this->get('/my-portfolio')->assertInertia(fn (Assert $page) => $page->where('cases.0.changed', true)->where('cases.0.content.title', 'A renamed case study'));
        $this->patch('/my-portfolio/'.$case->id, ['action' => 'publish']);
        $this->get($public)->assertInertia(fn (Assert $page) => $page->where('case.content.title', 'A renamed case study'));

        $this->patch('/my-portfolio/'.$case->id, ['action' => 'hide']);
        $this->get($public)->assertNotFound();
        $this->patch('/my-portfolio/'.$case->id, ['action' => 'show']);
        $this->get($public)->assertOk();

        // Q74: a private profile hides its cases, direct links included; a suspended owner too.
        $this->patch('/my-profile/publication', ['published' => false]);
        $this->get($public)->assertNotFound();
        $this->patch('/my-profile/publication', ['published' => true]);
        $this->get($public)->assertOk();
        $freelancer->forceFill(['status' => AccountStatus::Suspended])->save();
        $this->get($public)->assertNotFound();
        $this->post('/my-portfolio', $this->content())->assertForbidden();
        $this->patch('/my-portfolio/'.$case->id, ['action' => 'publish'])->assertForbidden();
        $freelancer->forceFill(['status' => AccountStatus::Active])->save();

        // Nobody else can read the working copy or act on the case.
        $other = User::factory()->create(['onboarding_completed_at' => now()]);
        $this->actingAs($other)->get('/my-portfolio/'.$case->id.'/edit')->assertNotFound();
        $this->put('/my-portfolio/'.$case->id, $this->content())->assertNotFound();
        $this->patch('/my-portfolio/'.$case->id, ['action' => 'hide'])->assertNotFound();
        $this->delete('/my-portfolio/'.$case->id)->assertNotFound();
        $this->get($public)->assertOk();

        $this->actingAs($freelancer)->delete('/my-portfolio/'.$case->id)->assertSessionHasNoErrors();
        $this->get($public)->assertNotFound();
        // A new minute, so the request limit does not answer before the portfolio limit.
        $this->travel(2)->minutes();
        for ($count = 0; $count < 12; $count++) {
            $this->post('/my-portfolio', $this->content())->assertSessionHasNoErrors();
        }
        $this->post('/my-portfolio', $this->content())->assertSessionHasErrors('case');
    }

    public function test_completed_elancer_work_is_published_only_by_its_clients_approval_of_the_exact_content(): void
    {
        $freelancer = $this->freelancer();
        [, $open] = $this->contract($freelancer, 'active');
        [$client, $contract] = $this->contract($freelancer);
        $this->actingAs($freelancer)->post('/my-portfolio', $this->content(['contract' => $open->id]))->assertSessionHasErrors('contract');
        $this->actingAs($client)->post('/my-portfolio', $this->content(['contract' => $contract->id]))->assertSessionHasErrors('contract');
        $this->actingAs($freelancer)->post('/my-portfolio', $this->content(['contract' => $contract->id]))->assertSessionHasNoErrors();
        $this->post('/my-portfolio', $this->content(['contract' => $contract->id]))->assertSessionHasErrors('contract');
        $case = PortfolioCase::query()->firstOrFail();
        $public = '/portfolio/'.$case->id;
        $consent = '/contracts/'.$contract->id.'/portfolio';

        // The owner cannot publish client work alone, and the client hears nothing before a request.
        $this->patch('/my-portfolio/'.$case->id, ['action' => 'publish'])->assertSessionHasErrors('case');
        $this->actingAs($client)->get('/contracts/'.$contract->id)->assertInertia(fn (Assert $page) => $page->where('portfolio', null));
        $this->patch($consent, ['action' => 'revoke'])->assertSessionHasErrors('portfolio');

        $this->actingAs($freelancer)->patch('/my-portfolio/'.$case->id, ['action' => 'request'])->assertSessionHasNoErrors();
        $this->patch('/my-portfolio/'.$case->id, ['action' => 'request'])->assertSessionHasErrors('case');
        $first = PortfolioApproval::query()->firstOrFail();
        $this->assertSame(1, $client->notifications()->where('data->kind', 'portfolio_requested')->count());
        $this->get($public)->assertNotFound();
        // Only the client answers.
        $this->patch($consent, ['action' => 'approve', 'approval' => $first->id])->assertForbidden();
        $this->actingAs(User::factory()->create(['onboarding_completed_at' => now()]))->patch($consent, ['action' => 'approve', 'approval' => $first->id])->assertNotFound();
        $this->get($public)->assertNotFound();

        // An edit made after the request is not what the client approves.
        $this->actingAs($freelancer)->put('/my-portfolio/'.$case->id, $this->content(['title' => 'Edited after the request']));
        $this->actingAs($client)->get('/contracts/'.$contract->id)->assertInertia(fn (Assert $page) => $page
            ->where('portfolio.pending.id', $first->id)->where('portfolio.pending.content.title', 'Bilingual booking platform')->where('portfolio.case_id', null)->where('portfolio.public', null));
        $this->patch($consent, ['action' => 'approve', 'approval' => $first->id + 1])->assertSessionHasErrors('portfolio');
        $this->patch($consent, ['action' => 'approve', 'approval' => $first->id])->assertSessionHasNoErrors();
        $this->assertSame(1, $freelancer->notifications()->where('data->kind', 'portfolio_approved')->count());
        $response = $this->get($public)->assertInertia(fn (Assert $page) => $page->where('case.content.title', 'Bilingual booking platform')->where('case.elancer_work', true));
        // Nothing from the contract is public.
        $props = $response->viewData('page')['props'];
        $shown = json_encode([$props['case'], $props['freelancer']]);
        $this->assertStringNotContainsString('Clara', $shown);
        $this->assertStringNotContainsString('750.25', $shown);

        // Q56: the approved version stays public while a changed one waits; declining changes nothing.
        $this->actingAs($freelancer)->patch('/my-portfolio/'.$case->id, ['action' => 'request'])->assertSessionHasNoErrors();
        $second = PortfolioApproval::query()->latest('id')->firstOrFail();
        $this->get($public)->assertInertia(fn (Assert $page) => $page->where('case.content.title', 'Bilingual booking platform'));
        $this->actingAs($client)->patch($consent, ['action' => 'decline', 'approval' => $second->id])->assertSessionHasNoErrors();
        $this->get($public)->assertInertia(fn (Assert $page) => $page->where('case.content.title', 'Bilingual booking platform'));
        $this->assertSame(1, $freelancer->notifications()->where('data->kind', 'portfolio_declined')->count());

        // Q53: withdrawing permission hides the case at once and closes an open request.
        $this->actingAs($freelancer)->patch('/my-portfolio/'.$case->id, ['action' => 'request'])->assertSessionHasNoErrors();
        $third = PortfolioApproval::query()->latest('id')->firstOrFail();
        $this->actingAs($client)->patch($consent, ['action' => 'revoke'])->assertSessionHasNoErrors();
        $this->get($public)->assertNotFound();
        $this->patch($consent, ['action' => 'approve', 'approval' => $third->id])->assertSessionHasErrors('portfolio');
        $this->assertSame(['revoked', 'declined', 'closed'], PortfolioApproval::query()->orderBy('id')->pluck('status')->all());
        $this->assertSame(1, $freelancer->notifications()->where('data->kind', 'portfolio_revoked')->count());
        // Nothing the owner can do alone brings it back, and the history is kept.
        $this->actingAs($freelancer)->patch('/my-portfolio/'.$case->id, ['action' => 'show']);
        $this->patch('/my-portfolio/'.$case->id, ['action' => 'publish'])->assertSessionHasErrors('case');
        $this->get($public)->assertNotFound();
        $this->delete('/my-portfolio/'.$case->id)->assertSessionHasErrors('case');
        $this->get('/contracts/'.$contract->id)->assertInertia(fn (Assert $page) => $page->where('portfolio.case_id', $case->id)->where('portfolio.public', null)->has('portfolio.history', 3));

        // A new request and a new approval are fresh consent.
        $this->patch('/my-portfolio/'.$case->id, ['action' => 'request'])->assertSessionHasNoErrors();
        $this->actingAs($client)->patch($consent, ['action' => 'approve', 'approval' => PortfolioApproval::query()->latest('id')->value('id')])->assertSessionHasNoErrors();
        $this->get($public)->assertInertia(fn (Assert $page) => $page->where('case.content.title', 'Edited after the request'));
        $this->assertNull($case->fresh()->revoked_at);
    }
}

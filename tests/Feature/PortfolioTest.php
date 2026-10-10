<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\Category;
use App\Models\Contract;
use App\Models\Offer;
use App\Models\PortfolioApproval;
use App\Models\PortfolioCase;
use App\Models\PortfolioImage;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
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

    /** The content check answers $action, or cannot be reached when it is null. */
    private function moderation(?string $action): void
    {
        config(['services.sightengine.user' => 'test-user', 'services.sightengine.secret' => 'test-secret', 'services.sightengine.workflow' => 'test-workflow']);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['api.sightengine.com/*' => $action === null ? Http::failedConnection()
            : Http::response(['status' => 'success', 'summary' => ['action' => $action], 'workflow' => ['id' => 'test-workflow']])]);
    }

    private function upload(PortfolioCase $case): TestResponse
    {
        // Wider than the stored limit, so the resize is exercised; a thin strip keeps the suite's memory low.
        static $png;
        $png ??= UploadedFile::fake()->image('screen.png', 1640, 82)->getContent();

        return $this->postJson('/my-portfolio/'.$case->id.'/images', ['image' => UploadedFile::fake()->createWithContent('screen.png', $png)]);
    }

    /** @param  list<int>  $images */
    private function save(PortfolioCase $case, array $images): TestResponse
    {
        return $this->put('/my-portfolio/'.$case->id, $this->content(['images' => array_map(fn (int $id) => ['id' => $id, 'alt' => 'The booking screen'], $images)]));
    }

    public function test_case_images_are_checked_before_storage_and_are_public_only_through_a_visible_public_version(): void
    {
        Storage::fake('uploads');
        $freelancer = $this->freelancer();
        $visitor = User::factory()->create(['onboarding_completed_at' => now()]);
        $this->actingAs($freelancer)->post('/my-portfolio', $this->content())->assertSessionHasNoErrors();
        $case = PortfolioCase::query()->firstOrFail();
        $url = fn (int $id) => '/portfolio/images/'.$id;

        // A rejected image and an unreachable check store nothing.
        $this->moderation('reject');
        $this->upload($case)->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->moderation(null);
        $this->upload($case)->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->assertSame(0, PortfolioImage::query()->count());
        $this->assertSame([], Storage::disk('uploads')->allFiles());

        $this->moderation('accept');
        $first = $this->upload($case)->assertOk()->json('id');
        $second = $this->upload($case)->assertOk()->json('id');
        $stored = PortfolioImage::query()->findOrFail($first);
        $this->assertStringStartsWith('portfolio-images/'.$case->id.'/', $stored->path);
        $size = getimagesizefromstring(Storage::disk('uploads')->get($stored->path));
        $this->assertSame(['image/jpeg', 1600, 80], [$size['mime'], $size[0], $size[1]]);

        // Another member cannot add to the case, read its image or list it in a case of their own.
        $this->actingAs($visitor)->post('/my-portfolio', $this->content())->assertSessionHasNoErrors();
        $theirs = PortfolioCase::query()->latest('id')->firstOrFail();
        $this->upload($case)->assertNotFound();
        $this->get($url($first))->assertNotFound();
        $this->save($theirs, [$first])->assertSessionHasErrors('images.0.id');

        // Saving lists the first image only; the one left out is removed. A description is required.
        $this->actingAs($freelancer)->put('/my-portfolio/'.$case->id, $this->content(['images' => [['id' => $first, 'alt' => '']]]))->assertSessionHasErrors('images.0.alt');
        $this->save($case, [$first])->assertSessionHasNoErrors();
        $this->assertNull(PortfolioImage::query()->find($second));
        $this->assertCount(1, Storage::disk('uploads')->allFiles());
        // The owner reads a draft's image; nobody else does.
        $this->get($url($first))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($visitor)->get($url($first))->assertNotFound();

        $this->actingAs($freelancer)->patch('/my-portfolio/'.$case->id, ['action' => 'publish'])->assertSessionHasNoErrors();
        $this->get('/portfolio/'.$case->id)->assertInertia(fn (Assert $page) => $page->where('case.content.images', [['id' => $first, 'alt' => 'The booking screen']]));
        $this->actingAs($visitor)->get($url($first))->assertOk();
        $this->app['auth']->forgetGuards();
        $this->get($url($first))->assertOk();

        // Hiding the case and a private profile stop the direct link too (Q74).
        $this->actingAs($freelancer)->patch('/my-portfolio/'.$case->id, ['action' => 'hide']);
        $this->actingAs($visitor)->get($url($first))->assertNotFound();
        $this->actingAs($freelancer)->patch('/my-portfolio/'.$case->id, ['action' => 'show']);
        $this->patch('/my-profile/publication', ['published' => false]);
        $this->actingAs($visitor)->get($url($first))->assertNotFound();
        $this->actingAs($freelancer)->patch('/my-profile/publication', ['published' => true]);

        // A saved change stays private: the public version keeps its image until the change is published.
        $third = $this->upload($case)->assertOk()->json('id');
        $this->save($case, [$third])->assertSessionHasNoErrors();
        $this->actingAs($visitor)->get($url($first))->assertOk();
        $this->get($url($third))->assertNotFound();
        $this->actingAs($freelancer)->patch('/my-portfolio/'.$case->id, ['action' => 'publish']);
        $this->actingAs($visitor)->get($url($third))->assertOk();
        $this->get($url($first))->assertNotFound();
        $this->assertNull(PortfolioImage::query()->find($first));

        // One version lists at most six images, and a full case sends nothing more for checking.
        for ($count = 0; $count < 29; $count++) {
            (new PortfolioImage)->forceFill(['portfolio_case_id' => $case->id, 'path' => 'portfolio-images/'.$case->id.'/'.$count.'.jpg', 'width' => 1, 'height' => 1])->save();
        }
        $this->travel(2)->minutes();
        $this->moderation('accept');
        $this->actingAs($freelancer)->upload($case)->assertUnprocessable()->assertJsonValidationErrors('image');
        Http::assertNothingSent();
        $this->save($case, PortfolioImage::query()->limit(7)->pluck('id')->all())->assertSessionHasErrors('images');

        $this->delete('/my-portfolio/'.$case->id)->assertSessionHasNoErrors();
        $this->assertSame([], Storage::disk('uploads')->allFiles('portfolio-images/'.$case->id));
    }

    public function test_the_client_approves_the_exact_images_and_withdrawing_permission_stops_their_direct_links(): void
    {
        Storage::fake('uploads');
        $this->moderation('accept');
        $freelancer = $this->freelancer();
        [$client, $contract] = $this->contract($freelancer);
        $visitor = User::factory()->create(['onboarding_completed_at' => now()]);
        $this->actingAs($freelancer)->post('/my-portfolio', $this->content(['contract' => $contract->id]))->assertSessionHasNoErrors();
        $case = PortfolioCase::query()->firstOrFail();
        $consent = '/contracts/'.$contract->id.'/portfolio';
        $url = fn (int $id) => '/portfolio/images/'.$id;
        $first = $this->upload($case)->assertOk()->json('id');
        $this->save($case, [$first])->assertSessionHasNoErrors();

        // The client sees nothing before a request, then exactly the images sent to them.
        $this->actingAs($client)->get($url($first))->assertNotFound();
        $this->actingAs($freelancer)->patch('/my-portfolio/'.$case->id, ['action' => 'request'])->assertSessionHasNoErrors();
        $second = $this->upload($case)->assertOk()->json('id');
        $this->save($case, [$first, $second])->assertSessionHasNoErrors();
        $this->actingAs($client)->get('/contracts/'.$contract->id)->assertInertia(fn (Assert $page) => $page->where('portfolio.pending.content.images', [['id' => $first, 'alt' => 'The booking screen']]));
        $this->get($url($first))->assertOk();
        $this->get($url($second))->assertNotFound();
        $this->actingAs($visitor)->get($url($first))->assertNotFound();

        // Approval publishes the images of that request, not the ones added afterwards.
        $this->actingAs($client)->patch($consent, ['action' => 'approve', 'approval' => PortfolioApproval::query()->latest('id')->value('id')])->assertSessionHasNoErrors();
        $this->actingAs($visitor)->get($url($first))->assertOk();
        $this->get($url($second))->assertNotFound();
        $this->get('/portfolio/'.$case->id)->assertInertia(fn (Assert $page) => $page->has('case.content.images', 1));

        // A changed set of images is a new request (Q52); the client reads it, visitors do not (Q56).
        $this->actingAs($freelancer)->patch('/my-portfolio/'.$case->id, ['action' => 'request'])->assertSessionHasNoErrors();
        $this->actingAs($client)->get($url($second))->assertOk();
        $this->actingAs($visitor)->get($url($second))->assertNotFound();

        // Q53: withdrawing permission stops the direct links at once, for the client as well.
        $this->actingAs($client)->patch($consent, ['action' => 'revoke'])->assertSessionHasNoErrors();
        $this->get($url($first))->assertNotFound();
        $this->get($url($second))->assertNotFound();
        $this->actingAs($visitor)->get($url($first))->assertNotFound();
        // The owner keeps a private copy, and images named by a request stay as history.
        $this->actingAs($freelancer)->get($url($first))->assertOk();
        $this->save($case, [])->assertSessionHasNoErrors();
        $this->assertSame(2, PortfolioImage::query()->count());
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
        $this->assertSame(1, $client->notifications()->get()->where('data.kind', 'portfolio_requested')->count());
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
        $this->assertSame(1, $freelancer->notifications()->get()->where('data.kind', 'portfolio_approved')->count());
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
        $this->assertSame(1, $freelancer->notifications()->get()->where('data.kind', 'portfolio_declined')->count());

        // Q53: withdrawing permission hides the case at once and closes an open request.
        $this->actingAs($freelancer)->patch('/my-portfolio/'.$case->id, ['action' => 'request'])->assertSessionHasNoErrors();
        $third = PortfolioApproval::query()->latest('id')->firstOrFail();
        $this->actingAs($client)->patch($consent, ['action' => 'revoke'])->assertSessionHasNoErrors();
        $this->get($public)->assertNotFound();
        $this->patch($consent, ['action' => 'approve', 'approval' => $third->id])->assertSessionHasErrors('portfolio');
        $this->assertSame(['revoked', 'declined', 'closed'], PortfolioApproval::query()->orderBy('id')->pluck('status')->all());
        $this->assertSame(1, $freelancer->notifications()->get()->where('data.kind', 'portfolio_revoked')->count());
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

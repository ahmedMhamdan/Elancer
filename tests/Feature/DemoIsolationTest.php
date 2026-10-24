<?php

namespace Tests\Feature;

use App\Actions\Conversations\HiringAccess;
use App\Actions\Demo\DemoBaseline;
use App\Actions\Demo\DemoResetRefused;
use App\Models\Category;
use App\Models\Contract;
use App\Models\ContractSubmission;
use App\Models\Offer;
use App\Models\PaymentAttempt;
use App\Models\PortfolioCase;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Plan 027: demo visitors act among demo accounts, apart from real members, and a reset takes it all back. */
class DemoIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const CLOSED = 'This part is switched off in the shared demo. Create your own free account to use it.';

    private const APART = 'Demo accounts and members work apart, so this is not available between them.';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('uploads');
        // Members are offered no provider here; a demo session gets the stand-in one regardless.
        config(['payments.simulator.enabled' => false, 'payments.stripe.secret' => null, 'payments.moyasar.secret' => null]);
    }

    /** A member with a profile; with $public, one the directory lists and who may apply. */
    private function member(string $email, bool $public = false): User
    {
        $user = User::factory()->create(['name' => ucfirst(Str::before($email, '@')).' Person', 'email' => $email, 'onboarding_completed_at' => now()]);
        $profile = $user->profile()->create(['headline' => 'Laravel developer', 'bio' => 'Building reliable applications.']);
        if ($public) {
            $profile->syncSkillTags([Skill::query()->orderBy('id')->value('name')]);
            $profile->forceFill(['published_at' => now()->subDay()])->save();
        }

        return $user;
    }

    private function project(User $owner, string $status = 'published', string $title = 'Build an application'): Project
    {
        $project = new Project;
        $project->forceFill(['user_id' => $owner->id, 'category_id' => Category::query()->firstOrCreate(['categoryname' => 'Development'])->id, 'title' => $title,
            'description' => 'A complete project brief for an application.', 'budget_min' => 100, 'budget_max' => 500, 'status' => $status, 'screening_questions' => [],
            'published_at' => $status === 'draft' ? null : now()->subDay(), 'application_closes_at' => now()->addDays(7)])->save();
        $project->skills()->sync(Skill::query()->orderBy('id')->limit(2)->pluck('id'));

        return $project;
    }

    /** A draft created and published by whoever is signed in, through the pages a client uses. */
    private function publish(Project $like, string $title): Project
    {
        $this->post('/my-projects')->assertRedirect();
        $project = Project::query()->latest('id')->firstOrFail();
        $this->postJson('/my-projects/'.$project->id.'/publish', ['version' => 1, 'title' => $title, 'description' => str_repeat('A clear and complete brief. ', 3),
            'category_id' => $like->category_id, 'skills' => [Skill::query()->orderBy('id')->value('id')], 'budget_min' => 200, 'budget_max' => 400,
            'application_closes_at' => now()->addDays(5)->toIso8601String(), 'screening_questions' => []])->assertOk()->assertJsonPath('project.status', 'published');

        return $project;
    }

    /** @return array<string, mixed> */
    private function application(): array
    {
        return ['action' => 'submit', 'version' => 0, 'price' => '300', 'duration_days' => 7, 'message' => 'I have built several applications like this one.', 'answers' => [], 'samples' => []];
    }

    private function apply(Project $project): Proposal
    {
        $this->putJson('/jobs/'.$project->id.'/proposal', $this->application())->assertOk();

        return Proposal::query()->latest('id')->firstOrFail();
    }

    /** Offer, acceptance, funding through the stand-in provider and a delivery, between the client signed in and $freelancer. */
    private function contract(User $client, User $freelancer, Proposal $proposal, callable $become): Contract
    {
        $become($client);
        $this->post('/proposals/'.$proposal->id.'/conversation', ['body' => 'Thank you for applying.', 'client_token' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->post('/proposals/'.$proposal->id.'/offer', ['scope' => 'Build a bilingual application with the agreed account and project workflows.', 'deliverables' => ['Application source code'],
            'amount' => '300', 'duration_days' => 7, 'revision_rounds' => 1, 'proposal_version' => 1, 'client_token' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $become($freelancer);
        $this->patch('/offers/'.Offer::query()->latest('id')->value('id'), ['action' => 'accept', 'version' => 1])->assertSessionHasNoErrors();
        $contract = Contract::query()->latest('id')->firstOrFail();
        $become($client);
        $this->post('/contracts/'.$contract->id.'/payments', ['provider' => 'simulator', 'client_token' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $attempt = PaymentAttempt::query()->latest('id')->firstOrFail();
        $this->get('/payments/'.$attempt->id.'/simulator')->assertOk();
        $this->post('/payments/'.$attempt->id.'/simulator', ['decision' => 'approve'])->assertRedirect('/payments/'.$attempt->id.'/return');
        $this->get('/payments/'.$attempt->id.'/return')->assertRedirect('/contracts/'.$contract->id);
        $this->assertSame('active', $contract->fresh()->status);
        $become($freelancer);
        $this->post('/contracts/'.$contract->id.'/deliveries', ['message' => 'The complete application is ready for your review.', 'complete' => true,
            'client_token' => (string) Str::uuid()])->assertSessionHasNoErrors();

        return $contract;
    }

    /** One hire from proposal to both reviews, played by one visitor moving between the two prepared accounts. Ends as the freelancer. */
    private function hire(User $client, User $freelancer, Project $project): Contract
    {
        $become = function (User $account): void {
            if (auth()->id() !== $account->id) {
                $this->post('/demo/switch')->assertRedirect('/dashboard');
            }
            $this->assertAuthenticatedAs($account);
            $this->assertTrue(session('demo'));
        };
        $become($freelancer);
        $contract = $this->contract($client, $freelancer, $this->apply($project), $become);
        $become($client);
        $this->post('/contracts/'.$contract->id.'/complete', ['submission' => ContractSubmission::query()->latest('id')->value('id')])->assertSessionHasNoErrors();
        $this->put('/contracts/'.$contract->id.'/review', ['rating' => 5, 'body' => 'Careful work, delivered on time.'])->assertSessionHasNoErrors();
        $become($freelancer);
        $this->put('/contracts/'.$contract->id.'/review', ['rating' => 4, 'body' => 'A clear brief and quick answers.'])->assertSessionHasNoErrors();

        return $contract->fresh();
    }

    /**
     * Every row of every table but the ones a reset is meant to write and the accounts table, which it never writes.
     *
     * @return array<string, list<string>>
     */
    private function rows(): array
    {
        $rows = [];
        foreach (Schema::getTables(Schema::getCurrentSchemaName()) as $table) {
            if (! in_array($table['name'], ['demo_baselines', 'moderation_events', 'users', 'migrations'], true)) {
                $rows[$table['name']] = DB::table($table['name'])->get()->map(fn (object $row): string => (string) json_encode((array) $row))->sort()->values()->all();
            }
        }

        return $rows;
    }

    public function test_a_demo_session_acts_among_demo_accounts_once_a_starting_point_is_saved(): void
    {
        $client = $this->member('client-01@demo.elancer.test');
        $freelancer = $this->member('freelancer-01@demo.elancer.test', true);
        $open = $this->project($client);
        $draft = $this->project($client, 'draft', 'A draft to finish');
        $account = fn (User $user): array => Arr::only($user->fresh()->getAttributes(), ['name', 'email', 'password', 'status', 'locale', 'two_factor_secret', 'email_preferences', 'email_verified_at']);
        $accounts = [$account($client), $account($freelancer)];

        // Nothing could be taken back yet, so the session only looks.
        $this->post('/demo/client')->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('auth.demo', true)->where('auth.demoActing', false)->where('auth.demoOther', 'freelancer'));
        $this->postJson('/my-projects')->assertForbidden();
        $this->post('/logout');

        DemoBaseline::save(null);
        $this->travel(5)->seconds();
        // The switch that turns acting off without a new starting point.
        config(['demo.actions' => false]);
        $this->post('/demo/client');
        $this->postJson('/my-projects')->assertForbidden();
        $this->post('/logout');
        config(['demo.actions' => true]);

        $this->post('/demo/client')->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('auth.demoActing', true)->where('auth.demoOther', 'freelancer'));
        // Moving to the other account keeps the session's number: a new one would answer a page opened meanwhile as a stranger and sign the visitor out.
        $session = session()->getId();
        $this->withCookie(session()->getName(), $session)->post('/demo/switch')->assertRedirect('/dashboard');
        $this->assertSame($session, session()->getId());
        $this->assertAuthenticatedAs($freelancer);
        $this->assertTrue(session('demo'));
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('auth.user.email', $freelancer->email)->where('auth.user.workspace_role', 'freelancer')->where('auth.demoOther', 'client'));
        $this->post('/demo/switch')->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($client);
        $this->defaultCookies = [];
        $kept = $this->publish($open, 'A landing page for a bakery');
        $hired = $this->publish($open, 'A booking form for a clinic');
        $this->post('/my-projects/'.$open->id.'/clarifications', ['body' => 'The deadline can move by a week.'])->assertSessionHasNoErrors();
        $this->get('/contracts')->assertOk();

        // The shared account's public face, every file, reports, blocking and credentials stay closed.
        foreach ([['patch', '/my-profile', ['headline' => 'Changed']], ['patch', '/my-profile/publication', ['published' => false]], ['post', '/reports', []],
            ['post', '/freelancers/'.$freelancer->profile->id.'/block', []], ['delete', '/my-projects/'.$draft->id.'/permanent', []], ['patch', '/workspace', ['workspace' => 'freelancer']],
            ['patch', '/settings/profile', ['name' => 'Taken Over', 'email' => 'mine@example.com']], ['post', '/my-portfolio', []], ['post', '/my-profile/identity', []],
            ['post', '/my-projects/'.$draft->id.'/attachments', []], ['post', '/user/two-factor-authentication', []]] as [$method, $address, $data]) {
            $this->{$method.'Json'}($address, $data)->assertForbidden()->assertExactJson(['message' => self::CLOSED]);
        }
        $this->get('/settings/profile')->assertRedirect('/dashboard');
        // No file enters storage through an account anyone can open, even on an action that is open.
        $this->postJson('/my-projects/'.$open->id.'/clarifications', ['body' => 'A note with a file.', 'file' => UploadedFile::fake()->create('scope.pdf', 20, 'application/pdf')])
            ->assertForbidden()->assertExactJson(['message' => self::CLOSED]);

        $contract = $this->hire($client, $freelancer, $hired);
        $this->assertSame('completed', $contract->status);
        $this->assertSame('simulator', PaymentAttempt::query()->value('provider'));
        $this->assertNotNull(DB::table('demo_baselines')->value('changed_at'));
        $this->assertSame($accounts, [$account($client), $account($freelancer)]);
        Storage::disk('uploads')->assertDirectoryEmpty('/');

        // Inside the demo all of it shows.
        $inside = function () use ($open, $hired, $freelancer): void {
            $this->get('/jobs/'.$hired->id)->assertOk();
            $this->get('/jobs')->assertInertia(fn (Assert $page) => $page->has('projects.data', 2));
            $this->get('/')->assertInertia(fn (Assert $page) => $page->where('totals.projects', 2));
            $this->get('/jobs/'.$open->id)->assertInertia(fn (Assert $page) => $page->has('clarifications', 1)->where('client.completed', 1)->where('client.rating.count', 1));
            $this->get('/freelancers/'.$freelancer->profile->id)->assertInertia(fn (Assert $page) => $page->where('record.completed', 1)->where('record.rating.count', 1)->has('reviews', 1));
        };
        $inside();
        $this->post('/logout');

        // A guest and a real member see the demo as it was saved: nothing a visitor wrote.
        $outside = function () use ($open, $kept, $hired, $freelancer): void {
            $this->get('/jobs/'.$hired->id)->assertNotFound();
            $this->get('/jobs/'.$kept->id)->assertNotFound();
            $this->get('/jobs')->assertInertia(fn (Assert $page) => $page->has('projects.data', 1)->where('projects.data.0.id', $open->id));
            $this->get('/')->assertInertia(fn (Assert $page) => $page->where('totals.projects', 1)->has('projects', 1));
            $this->get('/jobs/'.$open->id)->assertInertia(fn (Assert $page) => $page->has('clarifications', 0)->where('client.completed', 0)->where('client.rating.count', 0)->has('client.reviews', 0));
            $this->get('/freelancers/'.$freelancer->profile->id)->assertInertia(fn (Assert $page) => $page->where('record.completed', 0)->where('record.rating.count', 0)->has('reviews', 0));
            $this->get('/freelancers')->assertInertia(fn (Assert $page) => $page->where('freelancers.data.0.rating.count', 0));
        };
        $outside();
        $this->actingAs($this->member('ruth@example.com'));
        $outside();
        // The account entered with its own credentials is inside as well.
        $this->actingAs($client);
        $inside();
    }

    public function test_demo_accounts_and_real_members_cannot_hire_each_other(): void
    {
        $demoClient = $this->member('client-01@demo.elancer.test');
        $demoFreelancer = $this->member('freelancer-01@demo.elancer.test', true);
        $realClient = $this->member('rana@example.com');
        $realFreelancer = $this->member('omar@example.com', true);
        $demoProject = $this->project($demoClient);
        $realProject = $this->project($realClient);

        // A real freelancer may try applying to a demo project (the next test follows that); a member's project works as before.
        $this->actingAs($realFreelancer)->get('/jobs/'.$demoProject->id)->assertOk()->assertInertia(fn (Assert $page) => $page->where('application.apart', 'demo'));
        $this->get('/jobs/'.$demoProject->id.'/apply')->assertOk()->assertInertia(fn (Assert $page) => $page->where('trial', true));
        $this->post('/jobs/'.$demoProject->id.'/save')->assertSessionHasNoErrors();
        $this->get('/jobs/'.$realProject->id)->assertInertia(fn (Assert $page) => $page->where('application.apart', null));
        $this->get('/jobs/'.$realProject->id.'/apply')->assertOk()->assertInertia(fn (Assert $page) => $page->where('trial', false));

        // A real client looks at a demo freelancer and cannot invite them.
        $this->actingAs($realClient)->get('/freelancers/'.$demoFreelancer->profile->id)->assertInertia(fn (Assert $page) => $page->where('canContact', true)->where('apart', 'demo'));
        $this->get('/freelancers/'.$demoFreelancer->profile->id.'/invite')->assertForbidden();
        $this->post('/freelancers/'.$demoFreelancer->profile->id.'/invite', ['project_id' => $realProject->id])->assertForbidden();
        $this->get('/freelancers/'.$realFreelancer->profile->id)->assertInertia(fn (Assert $page) => $page->where('canContact', true)->where('apart', null));
        $this->get('/freelancers/'.$realFreelancer->profile->id.'/invite')->assertOk();

        // The rule belongs to the account, so it holds for a demo account entered with its own credentials too.
        $this->actingAs($demoFreelancer)->get('/jobs/'.$realProject->id)->assertInertia(fn (Assert $page) => $page->where('application.apart', 'member'));
        $this->get('/jobs/'.$realProject->id.'/apply')->assertForbidden();
        $this->putJson('/jobs/'.$realProject->id.'/proposal', $this->application())->assertForbidden();
        $this->get('/jobs/'.$demoProject->id.'/apply')->assertOk();
        $this->actingAs($demoClient)->get('/freelancers/'.$realFreelancer->profile->id)->assertInertia(fn (Assert $page) => $page->where('canContact', true)->where('apart', 'member'));
        $this->post('/freelancers/'.$realFreelancer->profile->id.'/invite', ['project_id' => $demoProject->id])->assertForbidden();
        $this->get('/freelancers/'.$demoFreelancer->profile->id.'/invite')->assertOk();

        // And for a visitor who came through the demo button and may act.
        DemoBaseline::save(null);
        $this->post('/logout');
        $this->post('/demo/freelancer')->assertRedirect('/dashboard');
        $this->putJson('/jobs/'.$realProject->id.'/proposal', $this->application())->assertForbidden()->assertJsonPath('message', self::APART);
        $this->putJson('/jobs/'.$demoProject->id.'/proposal', $this->application())->assertOk();
        $this->post('/logout');

        // A proposal that crosses goes no further, whichever way it came to be.
        $crossing = new Proposal;
        $crossing->forceFill(['project_id' => $demoProject->id, 'user_id' => $realFreelancer->id, 'status' => 'submitted', 'version' => 1,
            'content' => ['price' => '300.00', 'duration_days' => 7], 'submitted_at' => now()])->save();
        $this->assertFalse(HiringAccess::writable($crossing));
        $this->actingAs($demoClient)->get('/proposals/'.$crossing->id.'/offer')->assertForbidden();
        $this->post('/proposals/'.$crossing->id.'/conversation', ['body' => 'Hello there.', 'client_token' => (string) Str::uuid()])->assertStatus(409);

        $this->assertSame(0, DB::table('invitations')->count());
        $this->assertSame(0, DB::table('conversations')->count());
        $this->assertSame(0, DB::table('offers')->count());
        $this->assertSame([$demoFreelancer->id, $realFreelancer->id], Proposal::query()->orderBy('id')->pluck('user_id')->all());

        // Nobody joins the demo by choosing an address on its domain.
        $this->post('/logout');
        $this->post('/register', ['name' => 'Mallory', 'email' => 'mallory@demo.elancer.test', 'password' => 'A-str0ng-passphrase!', 'password_confirmation' => 'A-str0ng-passphrase!'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($realClient)->patch('/settings/profile', ['name' => $realClient->name, 'email' => 'rana@DEMO.elancer.test'])->assertSessionHasErrors('email');
        $this->assertSame('rana@example.com', $realClient->fresh()->email);
    }

    /** Ahmed, 2026-10-13: a registrant can try applying while almost every project is a demo one. The application is theirs alone. */
    public function test_a_member_tries_applying_to_a_demo_project_and_the_application_stays_with_the_member(): void
    {
        $demoClient = $this->member('client-01@demo.elancer.test');
        $demoFreelancer = $this->member('freelancer-01@demo.elancer.test', true);
        $omar = $this->member('omar@example.com', true);
        $project = $this->project($demoClient);
        $this->actingAs($demoFreelancer);
        $inside = $this->apply($project);
        DemoBaseline::save(null);
        $this->travel(5)->seconds();
        $told = fn (User $user): int => DB::table('notifications')->where('notifiable_id', $user->id)->count();
        $this->assertSame([1, 0], [$told($demoClient), $told($omar)]);

        // The member applies, changes the proposal and finds it under their own proposals, marked as a sample.
        $this->actingAs($omar)->get('/jobs/'.$project->id)->assertInertia(fn (Assert $page) => $page->where('application.apart', 'demo')->where('project.proposals_received', 1));
        $proposal = $this->apply($project);
        $this->assertSame([$omar->id, 'submitted'], [$proposal->user_id, $proposal->status]);
        $this->putJson('/jobs/'.$project->id.'/proposal', [...$this->application(), 'version' => 1, 'price' => '350'])->assertOk();
        $this->get('/proposals/'.$proposal->id)->assertOk()->assertInertia(fn (Assert $page) => $page->where('trial', true)->where('author', true)->where('canEdit', true)
            ->where('canStartConversation', false)->where('proposal.content.price', '350.00'));
        $this->get('/my-proposals')->assertInertia(fn (Assert $page) => $page->has('proposals.data', 1)->where('proposals.data.0.trial', true));
        $this->get('/jobs/'.$project->id)->assertInertia(fn (Assert $page) => $page->where('project.proposals_received', 2)->where('application.proposal.id', $proposal->id));

        // The shared demo account is not told of it and is never shown it, entered with its own credentials or through the button.
        $this->assertSame(1, $told($demoClient));
        $unseen = function () use ($project, $proposal, $inside): void {
            $this->get('/my-projects/'.$project->id.'/proposals')->assertInertia(fn (Assert $page) => $page->has('proposals.data', 1)->where('proposals.data.0.id', $inside->id));
            $this->get('/proposals/'.$inside->id)->assertOk();
            $this->get('/proposals/'.$proposal->id)->assertNotFound();
            $this->get('/my-projects/'.$project->id.'/proposals/compare?ids[]='.$inside->id.'&ids[]='.$proposal->id)->assertNotFound();
            $this->patchJson('/proposals/'.$proposal->id.'/review', ['action' => 'decline', 'organization' => 'archived', 'version' => 2])->assertNotFound();
            $this->get('/proposals/'.$proposal->id.'/offer')->assertForbidden();
            $this->post('/proposals/'.$proposal->id.'/conversation', ['body' => 'Hello there.', 'client_token' => (string) Str::uuid()])->assertStatus(409);
        };
        $this->actingAs($demoClient);
        $unseen();
        $this->post('/logout');
        $this->post('/demo/client')->assertRedirect('/dashboard');
        $unseen();
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('overview.client', true)->where('overview.counts.proposals', 1)->where('overview.work.0.count', 1)
            ->where('overview.activity', fn ($activity): bool => ! str_contains((string) json_encode($activity), 'Omar')));

        // A visitor's note on the project is not sent to the member, and a project a visitor publishes takes no member's application.
        $this->post('/my-projects/'.$project->id.'/clarifications', ['body' => 'A note added by a visitor.'])->assertSessionHasNoErrors();
        $new = $this->publish($project, 'A booking form for a clinic');
        $this->post('/logout');
        $this->assertSame([1, 0], [$told($demoFreelancer), $told($omar)]);
        $this->actingAs($omar)->get('/jobs/'.$new->id)->assertNotFound();
        $this->get('/jobs/'.$new->id.'/apply')->assertStatus(409);
        $this->putJson('/jobs/'.$new->id.'/proposal', $this->application())->assertStatus(409);

        // So a reset is never held up by a member's application, and it leaves the application as it is.
        $kept = fn (): array => [DB::table('proposals')->where('user_id', $omar->id)->get()->all(), DB::table('proposal_events')->where('proposal_id', $proposal->id)->orderBy('id')->get()->all()];
        $before = $kept();
        $this->assertCount(2, $before[1]);
        DemoBaseline::restore(null);
        $this->assertEquals($before, $kept());
        $this->assertNull(Project::withTrashed()->find($new->id));
        $this->assertNull(DB::table('demo_baselines')->orderByDesc('id')->value('problem'));

        // The member withdraws it like any other proposal. Nothing else exists between the two sides.
        $this->actingAs($omar)->post('/proposals/'.$proposal->id.'/withdraw', ['version' => 2])->assertSessionHasNoErrors();
        $this->assertSame('withdrawn', $proposal->fresh()->status);
        $this->assertSame([0, 0, 0], [DB::table('conversations')->count(), DB::table('offers')->count(), DB::table('invitations')->count()]);
        $this->assertSame(0, $told($omar));
    }

    public function test_a_reset_returns_the_demo_to_its_starting_point_and_leaves_members_alone(): void
    {
        $client = $this->member('client-01@demo.elancer.test');
        $freelancer = $this->member('freelancer-01@demo.elancer.test', true);
        $second = $this->member('freelancer-02@demo.elancer.test', true);
        $rana = $this->member('rana@example.com');
        $omar = $this->member('omar@example.com', true);
        $signIn = fn (User $user) => $this->actingAs($user);

        // The demo as prepared: a draft, an open project with a note, an invitation and an applicant in conversation, and a funded contract awaiting review.
        $draft = $this->project($client, 'draft', 'A draft to finish');
        $open = $this->project($client);
        DB::table('project_clarifications')->insert(['project_id' => $open->id, 'body' => 'An earlier note from the client.', 'created_at' => now()]);
        $this->actingAs($client)->post('/freelancers/'.$freelancer->profile->id.'/invite', ['project_id' => $open->id])->assertSessionHasNoErrors();
        $invitation = (int) DB::table('invitations')->value('id');
        $this->actingAs($second);
        $earlier = $this->apply($open);
        $this->actingAs($client)->post('/proposals/'.$earlier->id.'/conversation', ['body' => 'Could you start next week?', 'client_token' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $conversation = (int) DB::table('conversations')->value('id');
        $shop = $this->project($client, 'published', 'A finished shop');
        $this->actingAs($second);
        $proposal = $this->apply($shop);
        config(['payments.simulator.enabled' => true]);
        $contract = $this->contract($client, $second, $proposal, $signIn);
        config(['payments.simulator.enabled' => false]);
        $submission = (int) ContractSubmission::query()->value('id');
        $this->actingAs($client)->post('/freelancers/'.$second->profile->id.'/save')->assertSessionHasNoErrors();

        // Members' own work, some of it pointing at demo pages.
        $theirs = $this->project($rana, 'published', 'A member project');
        $this->actingAs($omar);
        $this->apply($theirs);
        $this->post('/jobs/'.$open->id.'/save')->assertSessionHasNoErrors();
        $this->post('/freelancers/'.$freelancer->profile->id.'/save')->assertSessionHasNoErrors();
        $this->actingAs($freelancer)->post('/jobs/'.$open->id.'/save')->assertSessionHasNoErrors();
        $this->post('/jobs/'.$theirs->id.'/save')->assertSessionHasNoErrors();
        $this->post('/saved/searches', ['name' => 'Laravel work', 'filters' => ['q' => 'laravel']])->assertSessionHasNoErrors();
        $search = (int) DB::table('saved_searches')->value('id');
        $this->post('/logout');

        DemoBaseline::save(null);
        $this->travel(5)->seconds();
        $before = $this->rows();
        $accounts = User::query()->orderBy('id')->get()->map(fn (User $user): array => Arr::except($user->getAttributes(), ['workspace_role', 'remember_token', 'updated_at']))->all();
        $this->assertGreaterThan(0, count($before['simulated_payments']));
        $this->assertGreaterThan(0, count($before['notifications']));

        // A visitor's session: new work, and changes to what was prepared.
        $this->post('/demo/client')->assertRedirect('/dashboard');
        $new = $this->publish($open, 'A booking form for a clinic');
        $this->patch('/my-projects/'.$open->id.'/cutoff', ['application_closes_at' => now()->addDays(20)->toIso8601String()])->assertSessionHasNoErrors();
        $this->post('/my-projects/'.$open->id.'/clarifications', ['body' => 'A note added by a visitor.'])->assertSessionHasNoErrors();
        $this->delete('/my-projects/'.$draft->id)->assertRedirect();
        $this->patch('/proposals/'.$earlier->id.'/review', ['action' => 'decline', 'organization' => 'archived', 'client_note' => 'Not this time.', 'version' => 1])->assertSessionHasNoErrors();
        $this->post('/messages/'.$conversation, ['body' => 'One more question from a visitor.', 'client_token' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->patch('/messages/'.$conversation.'/state', ['archived' => true])->assertSessionHasNoErrors();
        $this->post('/contracts/'.$contract->id.'/revisions', ['submission' => $submission, 'changes' => 'Please adjust the colours of the header.'])->assertSessionHasNoErrors();
        $this->post('/notifications/read')->assertRedirect();
        $this->delete('/freelancers/'.$second->profile->id.'/save')->assertSessionHasNoErrors();
        $this->hire($client, $freelancer, $new);
        $this->patch('/invitations/'.$invitation, ['action' => 'decline', 'version' => 1])->assertSessionHasNoErrors();
        $this->delete('/jobs/'.$open->id.'/save')->assertSessionHasNoErrors();
        $this->post('/jobs/'.$new->id.'/save')->assertSessionHasNoErrors();
        $this->patch('/saved/searches/'.$search, ['name' => 'Renamed by a visitor'])->assertSessionHasNoErrors();
        $this->post('/logout');
        // What a visitor cannot remove is removed here, to show that it returns as well.
        Project::withTrashed()->findOrFail($draft->id)->forceDelete();
        DB::table('notifications')->where('notifiable_id', $client->id)->delete();

        $changed = $this->rows();
        foreach (['projects', 'project_skill', 'project_clarifications', 'proposals', 'proposal_events', 'invitations', 'invitation_events', 'conversations', 'conversation_messages', 'offers', 'contracts',
            'contract_submissions', 'contract_revision_requests', 'contract_reviews', 'payment_attempts', 'payment_events', 'simulated_payments', 'saved_projects', 'saved_profiles', 'saved_searches',
            'notifications'] as $table) {
            $this->assertNotEquals($before[$table], $changed[$table], $table);
        }

        $counts = DemoBaseline::restore(null);
        $this->assertEquals($before, $this->rows());
        $this->assertGreaterThan(20, $counts['removed']);
        $this->assertGreaterThan(2, $counts['returned']);
        $this->assertGreaterThanOrEqual(5, $counts['restored']);
        $this->assertSame($accounts, User::query()->orderBy('id')->get()->map(fn (User $user): array => Arr::except($user->getAttributes(), ['workspace_role', 'remember_token', 'updated_at']))->all());
        $state = fn (): object => DB::table('demo_baselines')->orderByDesc('id')->first();
        $this->assertSame([null, null], [$state()->changed_since, $state()->changed_at]);
        $this->assertNotNull($state()->restored_at);
        // A second reset finds nothing to do.
        $this->assertSame(['removed' => 0, 'returned' => 0, 'restored' => 0], DemoBaseline::restore(null));

        // A member's row that depends on a row the reset would delete: it refuses and changes nothing.
        $this->post('/demo/client');
        $this->post('/my-projects')->assertRedirect();
        $this->post('/logout');
        $stray = (int) Project::query()->max('id');
        DB::table('saved_projects')->insert(['user_id' => $omar->id, 'project_id' => $stray, 'created_at' => now()]);
        $held = $this->rows();
        try {
            DemoBaseline::restore(null);
            $this->fail('The reset deleted a row a member depends on.');
        } catch (DemoResetRefused $refusal) {
            $this->assertSame('dependents|projects|saved_projects', $refusal->getMessage());
        }
        $this->assertEquals($held, $this->rows());
        $this->assertSame('dependents|projects|saved_projects', $state()->problem);
        // A visitor still enters, and the refused reset waits for an administrator instead of being tried by each of them.
        $this->travel(2)->hours();
        $this->post('/demo/client')->assertRedirect('/dashboard');
        $this->post('/logout');
        $this->assertEquals($held, $this->rows());
        DB::table('saved_projects')->where('project_id', $stray)->delete();
        DemoBaseline::restore(null);
        $this->assertEquals($before, $this->rows());
        $this->assertNull($state()->problem);

        // Not the accounts the copy was made from: nothing is written.
        $second->forceFill(['email' => 'someone-else@demo.elancer.test'])->save();
        try {
            DemoBaseline::restore(null);
            $this->fail('The reset ran against other accounts.');
        } catch (DemoResetRefused $refusal) {
            $this->assertSame('accounts', $refusal->getMessage());
        }
        $this->assertEquals($before, $this->rows());
    }

    /** Plan 034: the demo freelancer has public case studies, so a visitor's application may share one. */
    public function test_a_reset_takes_back_an_application_that_shares_a_case_study(): void
    {
        $client = $this->member('client-01@demo.elancer.test');
        $freelancer = $this->member('freelancer-01@demo.elancer.test', true);
        $content = ['title' => 'Booking dashboard', 'summary' => 'A short summary of the work that was done.', 'body' => str_repeat('What was built and how it helped. ', 3),
            'skills' => [], 'links' => []];
        $case = new PortfolioCase;
        $case->forceFill(['user_id' => $freelancer->id, 'content' => $content, 'public_content' => $content, 'published_at' => now()->subDay()])->save();
        $prepared = $this->project($client, 'published', 'A prepared project');
        $this->actingAs($freelancer)->putJson('/jobs/'.$prepared->id.'/proposal', [...$this->application(), 'cases' => [$case->id]])->assertOk();
        $this->post('/logout');
        $this->assertSame(1, DB::table('proposal_cases')->count());
        $other = $this->project($client, 'published', 'Another project');
        DemoBaseline::save(null);
        $this->travel(5)->seconds();
        $before = $this->rows();

        $this->post('/demo/freelancer')->assertRedirect('/dashboard');
        $this->putJson('/jobs/'.$other->id.'/proposal', [...$this->application(), 'cases' => [$case->id]])->assertOk();
        $this->post('/logout');
        $this->assertSame(2, DB::table('proposal_cases')->count());

        // Before plan 034 the reset did not know these rows and refused with "dependents|proposals|proposal_cases".
        $this->assertGreaterThan(0, DemoBaseline::restore(null)['removed']);
        $this->assertEquals($before, $this->rows());

        // A starting point saved before these tables were listed holds nothing for them: their rows stay as they are.
        $latest = DB::table('demo_baselines')->orderByDesc('id')->first();
        $copy = json_decode((string) gzdecode((string) base64_decode($latest->payload)), true);
        unset($copy['tables']['proposal_cases'], $copy['tables']['proposal_files'], $copy['tables']['message_files']);
        DB::table('demo_baselines')->where('id', $latest->id)->update(['payload' => base64_encode((string) gzencode((string) json_encode($copy), 6))]);
        $this->assertSame(['removed' => 0, 'returned' => 0, 'restored' => 0], DemoBaseline::restore(null));
        $this->assertEquals($before, $this->rows());
    }

    public function test_a_visitor_entering_resets_a_demo_left_changed_and_quiet(): void
    {
        $this->member('client-01@demo.elancer.test');
        $this->member('freelancer-01@demo.elancer.test', true);
        DemoBaseline::save(null);
        $resets = fn (): int => DB::table('moderation_events')->where('action', 'demo_reset')->count();
        $visit = function (string $role, bool $draft = false): void {
            $this->post('/demo/'.$role)->assertRedirect('/dashboard');
            if ($draft) {
                $this->post('/my-projects')->assertRedirect();
            }
            $this->post('/logout');
        };

        // A clean demo is never reset.
        $this->travel(1)->days();
        $visit('client', true);
        $this->assertSame([0, 1], [$resets(), Project::query()->count()]);
        // The next visitor soon after finds the first one's work.
        $this->travel(10)->minutes();
        $visit('freelancer');
        $this->assertSame([0, 1], [$resets(), Project::query()->count()]);
        // After half an hour without a change, the next one starts from the starting point.
        $this->travel(31)->minutes();
        $visit('client');
        $this->assertSame([1, 0], [$resets(), Project::query()->count()]);
        $this->assertSame(['automatic' => true, 'removed' => 1, 'returned' => 0, 'restored' => 0],
            json_decode((string) DB::table('moderation_events')->where('action', 'demo_reset')->value('reason'), true));

        // A demo that is never quiet is still reset once it has stayed changed for twelve hours.
        $visit('client', true);
        $this->travel(11)->hours();
        DB::table('demo_baselines')->update(['changed_at' => now()->subMinute()]);
        $visit('freelancer');
        $this->assertSame([1, 1], [$resets(), Project::query()->count()]);
        $this->travel(2)->hours();
        DB::table('demo_baselines')->update(['changed_at' => now()->subMinute()]);
        $visit('freelancer');
        $this->assertSame([2, 0], [$resets(), Project::query()->count()]);

        // The same rule from the scheduler, and the plain command.
        $visit('client', true);
        $this->artisan('demo:reset', ['--due' => true])->assertExitCode(0);
        $this->assertSame([2, 1], [$resets(), Project::query()->count()]);
        $this->travel(31)->minutes();
        $this->artisan('demo:reset', ['--due' => true])->assertExitCode(0);
        $this->assertSame([3, 0], [$resets(), Project::query()->count()]);
        $this->artisan('demo:reset')->assertExitCode(0);
        $this->artisan('demo:save')->assertExitCode(0);
        $this->assertSame(2, DB::table('demo_baselines')->count());
        $this->artisan('demo:save')->assertExitCode(0);
        // The copy before the newest is kept; older ones are not.
        $this->assertSame(2, DB::table('demo_baselines')->count());
    }

    public function test_limits_count_each_demo_visitor_and_not_the_shared_account(): void
    {
        $client = $this->member('client-01@demo.elancer.test');
        $draft = $this->project($client, 'draft', 'A draft to finish');
        DemoBaseline::save(null);
        $this->post('/demo/client')->assertRedirect('/dashboard');

        // Ten publications a minute: the first visitor uses theirs up; a second visitor in the same account has their own.
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->postJson('/my-projects/'.$draft->id.'/publish', [])->assertStatus(422);
        }
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->postJson('/my-projects/'.$draft->id.'/publish', [])->assertStatus(429);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])->postJson('/my-projects/'.$draft->id.'/publish', [])->assertStatus(422);
    }

    public function test_only_super_administrators_save_the_starting_point_and_reset(): void
    {
        $client = $this->member('client-01@demo.elancer.test');
        $this->member('freelancer-01@demo.elancer.test', true);
        $this->project($client);
        $proof = fn (User $user): array => ['admin.two_factor_proof' => $user->id.':'.hash('sha256', (string) $user->two_factor_secret)];
        $admin = fn (bool $super): User => User::factory()->withTwoFactor()->create(['is_admin' => true, 'is_super_admin' => $super, 'onboarding_completed_at' => now()]);

        $this->get('/admin/demo')->assertRedirect('/login');
        $regular = $admin(false);
        $this->actingAs($regular)->withSession($proof($regular))->get('/admin/demo')->assertForbidden();
        $this->post('/admin/demo/baseline')->assertForbidden();
        $this->post('/admin/demo/reset')->assertForbidden();
        $super = $admin(true);
        $this->actingAs($super)->get('/admin/demo')->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/categories/security'));
        $this->post('/admin/demo/baseline')->assertForbidden();
        $this->assertSame(0, DB::table('demo_baselines')->count());

        $this->actingAs($super)->withSession($proof($super));
        $this->get('/admin/demo')->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/demo/index')->where('baseline', null)->where('accounts', 2)
            ->where('entry', ['client', 'freelancer'])->where('actions', true)->where('rules', ['idle_minutes' => 30, 'max_hours' => 12])->has('events', 0));
        // Nothing to return to yet.
        $this->from('/admin/demo')->post('/admin/demo/reset')->assertRedirect('/admin/demo')->assertSessionHas('demo_notice', 'refused|none');

        $this->from('/admin/demo')->post('/admin/demo/baseline')->assertRedirect('/admin/demo')->assertSessionHas('demo_notice', 'saved');
        $this->get('/admin/demo')->assertInertia(fn (Assert $page) => $page->where('baseline.accounts', 2)->where('baseline.taken_by', $super->name)->where('baseline.changed_at', null)
            ->where('baseline.restored_at', null)->where('baseline.problem', null)->where('baseline.rows', fn (int $rows): bool => $rows >= 5)
            ->has('events', 1)->where('events.0.action', 'demo_saved')->where('events.0.actor', $super->name)->where('events.0.details.accounts', 2)
            // The copy itself, with every demo account's rows, never reaches the page.
            ->missing('baseline.payload')->missing('baseline.summary'));

        Project::query()->update(['title' => 'Rewritten by a visitor']);
        $this->from('/admin/demo')->post('/admin/demo/reset')->assertRedirect('/admin/demo')->assertSessionHas('demo_notice', 'reset');
        $this->assertSame('Build an application', Project::query()->value('title'));
        $this->get('/admin/demo')->assertInertia(fn (Assert $page) => $page->has('events', 2)->where('events.0.action', 'demo_reset')->where('events.0.actor', $super->name)
            ->where('events.0.details.automatic', false)->where('events.0.details.restored', 1)->where('baseline.restored_at', fn (?string $time): bool => $time !== null));
        $this->assertSame([$super->id, $super->id], DB::table('moderation_events')->where('target_type', 'demo')->pluck('actor_id')->all());
    }
}

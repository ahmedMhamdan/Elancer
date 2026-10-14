<?php

namespace Tests\Feature;

use App\Actions\Demo\DemoBaseline;
use App\Http\Controllers\Settings\ApiTokenController;
use App\Models\Category;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/** Plan 028: /api/v1 answers a bearer token and nothing else, by the rules the pages follow. */
class ApiV1Test extends TestCase
{
    use RefreshDatabase;

    private const PROJECT = ['id', 'title', 'excerpt', 'category', 'skills', 'budget', 'published_at', 'application_closes_at', 'open', 'proposals_received', 'attachments', 'url'];

    private const PROPOSAL = ['id', 'status', 'version', 'project', 'content', 'draft', 'submitted_at', 'updated_at', 'editable', 'sample'];

    /** A member with a profile; with $public, one the directory lists and who may apply. */
    private function member(string $email, bool $public = false): User
    {
        $user = User::factory()->create(['name' => ucfirst(Str::before($email, '@')).' Person', 'email' => $email, 'onboarding_completed_at' => now()]);
        $user->profile()->create(['headline' => 'Laravel developer', 'bio' => 'Building reliable applications.']);
        if ($public) {
            $this->publishProfile($user);
        }

        return $user;
    }

    private function publishProfile(User $user): void
    {
        $user->profile->syncSkillTags([Skill::query()->orderBy('id')->value('name')]);
        $user->profile->forceFill(['published_at' => now()->subDay()])->save();
    }

    /** @param array<string, mixed> $attributes */
    private function project(User $owner, array $attributes = []): Project
    {
        $project = new Project;
        $project->forceFill(['user_id' => $owner->id, 'category_id' => Category::query()->firstOrCreate(['categoryname' => 'Development'])->id, 'title' => 'Build a booking application',
            'description' => 'A complete project brief for a booking application.', 'budget_min' => 100, 'budget_max' => 500, 'status' => 'published', 'screening_questions' => [],
            'published_at' => now()->subDay(), 'application_closes_at' => now()->addDays(7), ...$attributes])->save();
        $project->skills()->sync(Skill::query()->orderBy('id')->limit(2)->pluck('id'));

        return $project;
    }

    /** @param list<string> $abilities */
    private function token(User $user, array $abilities = ApiTokenController::ABILITIES, int $days = 30): string
    {
        return $user->createToken('A script', $abilities, now()->addDays($days))->plainTextToken;
    }

    /** Each call stands for a separate program's request: no header and no signed-in account is carried over from the one before. */
    private function as(?string $token): static
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        return $token === null ? $this : $this->withToken($token);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function application(array $overrides = []): array
    {
        return ['action' => 'submit', 'version' => 0, 'price' => '300', 'duration_days' => 7, 'message' => 'I have built several applications like this one.', 'answers' => [], 'samples' => [], ...$overrides];
    }

    public function test_a_request_needs_a_usable_token_that_holds_the_ability_the_route_names(): void
    {
        $client = $this->member('rana@example.com');
        $omar = $this->member('omar@example.com', true);
        $project = $this->project($client);
        $routes = [['get', '/api/v1/me'], ['get', '/api/v1/projects'], ['get', '/api/v1/projects/'.$project->id], ['get', '/api/v1/me/proposals'], ['get', '/api/v1/me/proposals/1'],
            ['put', '/api/v1/projects/'.$project->id.'/proposal'], ['post', '/api/v1/me/proposals/1/withdraw']];

        // No token and a made-up one: nobody.
        foreach ($routes as [$method, $address]) {
            $this->as(null)->{$method.'Json'}($address)->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
        }
        $this->as('1|elancer_'.str_repeat('a', 48))->getJson('/api/v1/me')->assertUnauthorized();
        // A member signed in to the pages, without a token: the API has no session, so nobody either.
        $this->as(null)->actingAs($omar, 'web')->getJson('/api/v1/me')->assertUnauthorized();
        $this->as(null)->actingAs($omar, 'web')->putJson('/api/v1/projects/'.$project->id.'/proposal', $this->application())->assertUnauthorized();
        $this->assertSame(0, Proposal::query()->count());

        // Each ability opens its own routes and no other; `me` answers any token.
        foreach (['projects:read' => [1, 2], 'proposals:read' => [3, 4], 'proposals:write' => [5, 6]] as $ability => $open) {
            $token = $this->token($omar, [$ability]);
            foreach ($routes as $index => [$method, $address]) {
                $status = $this->as($token)->{$method.'Json'}($address)->status();
                if ($index === 0 || in_array($index, $open, true)) {
                    $this->assertNotContains($status, [401, 403], $ability.' on '.$address);
                } else {
                    $this->assertSame(403, $status, $ability.' on '.$address);
                }
            }
        }

        // The secret is kept as a hash, using the token is recorded, and an expired or a revoked token stops working.
        $token = $this->token($omar, ['projects:read'], 7);
        $this->assertStringStartsWith('elancer_', Str::after($token, '|'));
        $this->assertSame(hash('sha256', Str::after($token, '|')), DB::table('personal_access_tokens')->where('id', (int) Str::before($token, '|'))->value('token'));
        $this->as($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.token.abilities', ['projects:read']);
        $this->assertNotNull(PersonalAccessToken::findToken($token)?->last_used_at);
        $this->travel(8)->days();
        $this->as($token)->getJson('/api/v1/me')->assertUnauthorized();
        $kept = $this->token($omar);
        $this->as($kept)->getJson('/api/v1/me')->assertOk();
        PersonalAccessToken::findToken($kept)?->delete();
        $this->as($kept)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_the_account_behind_a_token_must_still_be_allowed(): void
    {
        $client = $this->member('rana@example.com');
        $project = $this->project($client);
        $omar = $this->member('omar@example.com', true);
        $token = $this->token($omar);
        $expires = PersonalAccessToken::findToken($token)?->expires_at?->toIso8601String();

        // Exactly these fields: nothing else of the account leaves.
        $this->as($token)->getJson('/api/v1/me')->assertOk()->assertExactJson(['data' => ['id' => $omar->id, 'name' => 'Omar Person', 'email' => 'omar@example.com', 'locale' => 'en',
            'status' => 'active', 'can_apply' => true, 'profile_published' => true, 'token' => ['name' => 'A script', 'abilities' => ApiTokenController::ABILITIES, 'expires_at' => $expires]]]);

        // Suspended (Q64): the account is told so and reads the public list as its pages do; proposals are closed to it.
        $omar->forceFill(['status' => 'suspended'])->save();
        $this->as($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.status', 'suspended')->assertJsonPath('data.can_apply', false);
        $this->as($token)->getJson('/api/v1/projects')->assertOk()->assertJsonCount(1, 'data');
        $this->as($token)->getJson('/api/v1/me/proposals')->assertForbidden();
        $this->as($token)->putJson('/api/v1/projects/'.$project->id.'/proposal', $this->application())->assertForbidden();
        $this->assertSame(0, Proposal::query()->count());

        // Deactivated, or with an address that is no longer verified: the token answers nothing.
        $omar->forceFill(['status' => 'deactivated'])->save();
        $this->as($token)->getJson('/api/v1/me')->assertForbidden()->assertJsonPath('message', 'This account cannot use the API.');
        $this->as($token)->getJson('/api/v1/projects')->assertForbidden();
        $omar->forceFill(['status' => 'active', 'email_verified_at' => null])->save();
        $this->as($token)->getJson('/api/v1/me')->assertForbidden();
        $omar->forceFill(['email_verified_at' => now()])->save();
        $this->as($token)->getJson('/api/v1/me')->assertOk();

        // A prepared demo account never holds a token; one that names it is refused whatever put it there.
        $demo = $this->member('freelancer-01@demo.elancer.test', true);
        $this->as($this->token($demo))->getJson('/api/v1/me')->assertForbidden()->assertJsonPath('message', 'This account cannot use the API.');
        $this->as($this->token($demo))->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v1/projects')->assertForbidden()
            ->assertJsonPath('message', 'لا يستطيع هذا الحساب استخدام الواجهة البرمجية.');
    }

    public function test_projects_are_the_public_ones_and_carry_only_the_listed_fields(): void
    {
        $client = $this->member('rana@example.com');
        $client->profile->forceFill(['company' => 'Rana Studio', 'country' => 'Jordan'])->save();
        $omar = $this->member('omar@example.com', true);
        $demoClient = $this->member('client-01@demo.elancer.test');
        $public = $this->project($client);
        $older = $this->project($client, ['title' => 'Design a clinic logo', 'published_at' => now()->subDays(3), 'application_closes_at' => now()->subDay()]);
        $draft = $this->project($client, ['status' => 'draft', 'published_at' => null]);
        $moderated = $this->project($client, ['moderated_at' => now()]);
        DB::table('project_clarifications')->insert(['project_id' => $public->id, 'body' => 'The deadline can move by a week.', 'created_at' => now()]);
        DemoBaseline::save(null);
        $this->travel(5)->seconds();
        // What a demo visitor publishes after the starting point stays inside the demo (plan 027).
        $inside = $this->project($demoClient, ['title' => 'Written by a demo visitor']);
        $token = $this->token($omar, ['projects:read']);

        // Open projects, newest first, as /jobs lists them.
        $this->as($token)->getJson('/api/v1/projects')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $public->id)
            ->assertJsonPath('meta.per_page', 20)->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0', fn (array $project): bool => array_keys($project) === self::PROJECT)
            ->assertJsonPath('data.0.budget', ['min' => '100.00', 'max' => '500.00', 'currency' => 'USD'])
            ->assertJsonPath('data.0.category', ['name' => 'Development', 'slug' => 'development'])
            ->assertJsonPath('data.0.open', true)->assertJsonPath('data.0.url', url('/jobs/'.$public->id));
        $this->as($token)->getJson('/api/v1/projects?status=all')->assertOk()->assertJsonPath('data.*.id', [$public->id, $older->id]);
        $this->as($token)->getJson('/api/v1/projects?status=all&per_page=1&page=2')->assertOk()->assertJsonPath('data.*.id', [$older->id])->assertJsonPath('meta.last_page', 2);
        $this->as($token)->getJson('/api/v1/projects?status=all&q=LOGO')->assertOk()->assertJsonPath('data.*.id', [$older->id]);
        $this->as($token)->getJson('/api/v1/projects?q=nothing-like-this')->assertOk()->assertJsonCount(0, 'data');
        // The same filter rules as the page, and a bounded page size.
        $this->as($token)->getJson('/api/v1/projects?per_page=51')->assertStatus(422)->assertJsonValidationErrors('per_page');
        $this->as($token)->getJson('/api/v1/projects?posted=never')->assertStatus(422)->assertJsonValidationErrors('posted');
        $this->as($token)->getJson('/api/v1/projects?budget_min=500&budget_max=100')->assertStatus(422);

        // The project page: the brief, its notes and the limited summary of the client. No contact detail, no account number.
        $detail = $this->as($token)->getJson('/api/v1/projects/'.$public->id)->assertOk()
            ->assertJsonPath('data', fn (array $project): bool => array_keys($project) === [...self::PROJECT, 'description', 'screening_questions', 'clarifications', 'client'])
            ->assertJsonPath('data.description', 'A complete project brief for a booking application.')
            ->assertJsonPath('data.clarifications.0.body', 'The deadline can move by a week.')
            ->assertJsonPath('data.client', ['name' => 'Rana Studio', 'country' => 'Jordan', 'member_since' => now()->format('Y'), 'completed' => 0, 'rating' => ['count' => 0, 'average' => null]]);
        foreach (['rana@example.com', 'user_id', 'moderated', 'Rana Person'] as $private) {
            $this->assertStringNotContainsString($private, (string) $detail->getContent());
        }
        // A project that is closed stays readable; one that is not public does not exist here.
        $this->as($token)->getJson('/api/v1/projects/'.$older->id)->assertOk()->assertJsonPath('data.open', false);
        foreach ([$draft->id, $moderated->id, $inside->id, 999999] as $missing) {
            $this->as($token)->getJson('/api/v1/projects/'.$missing)->assertNotFound();
        }
        $this->as($token)->getJson('/api/v1/projects?status=all&q=visitor')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_applying_through_the_api_follows_the_rules_of_the_pages(): void
    {
        $client = $this->member('rana@example.com');
        $omar = $this->member('omar@example.com');
        $project = $this->project($client);
        $address = '/api/v1/projects/'.$project->id.'/proposal';
        $token = $this->token($omar, ['proposals:read', 'proposals:write']);
        $told = fn (User $user): int => DB::table('notifications')->where('notifiable_id', $user->id)->count();

        // A private draft is kept without a published profile; submitting waits for one (Q34).
        $this->as($token)->putJson($address, $this->application(['action' => 'save']))->assertCreated()
            ->assertJsonPath('data', fn (array $proposal): bool => array_keys($proposal) === self::PROPOSAL)
            ->assertJsonPath('data.status', 'draft')->assertJsonPath('data.version', 1)->assertJsonPath('data.content', null)->assertJsonPath('data.draft.price', '300.00');
        $this->as($token)->putJson($address, $this->application(['version' => 1]))->assertStatus(422)->assertJsonPath('message', 'Publish your freelancer profile before applying.');
        $this->assertSame(0, $told($client));
        $this->publishProfile($omar);
        $this->as($token)->putJson($address, $this->application(['version' => 1]))->assertOk()->assertJsonPath('data.status', 'submitted')->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.content.price', '300.00')->assertJsonPath('data.draft', null)->assertJsonPath('data.editable', true)->assertJsonPath('data.sample', false);
        $proposal = Proposal::query()->firstOrFail();
        // What the page would have done: the client is told once, the shared profile is frozen, the submission is recorded.
        $this->assertSame(1, $told($client));
        $this->assertSame('Omar Person', $proposal->profile_snapshot['name'] ?? null);
        $this->assertSame(['submitted'], DB::table('proposal_events')->where('proposal_id', $proposal->id)->pluck('kind')->all());

        // A stale copy is refused with the current one, invalid input names its fields, and no caller sets what the server owns.
        $this->as($token)->putJson($address, $this->application(['version' => 1, 'price' => '900']))->assertStatus(409)->assertJsonPath('data.version', 2)->assertJsonPath('data.content.price', '300.00');
        $this->as($token)->putJson($address, $this->application(['version' => 2, 'message' => 'Too short']))->assertStatus(422)->assertJsonValidationErrors('message');
        $this->as($token)->putJson($address, $this->application(['version' => 2, 'status' => 'declined', 'client_note' => 'Mine']))->assertStatus(422)->assertJsonValidationErrors(['status', 'client_note']);
        $this->assertSame(['submitted', 2, '300.00'], [$proposal->fresh()->status, $proposal->fresh()->version, $proposal->fresh()->content['price']]);

        // The author reads their own proposals; the client's private labels and notes never leave.
        $proposal->forceFill(['organization' => 'shortlisted', 'client_note' => 'A strong candidate.'])->save();
        $list = $this->as($token)->getJson('/api/v1/me/proposals')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $proposal->id)
            ->assertJsonPath('data.0', fn (array $item): bool => array_keys($item) === self::PROPOSAL)->assertJsonPath('data.0.project.title', 'Build a booking application');
        $one = $this->as($token)->getJson('/api/v1/me/proposals/'.$proposal->id)->assertOk()->assertJsonPath('data.id', $proposal->id);
        foreach ([$list, $one] as $answer) {
            foreach (['shortlisted', 'A strong candidate.', 'profile_snapshot', 'organization', 'client_note'] as $private) {
                $this->assertStringNotContainsString($private, (string) $answer->getContent());
            }
        }

        // Another member's token: the proposal does not exist for it.
        $other = $this->token($this->member('lina@example.com', true), ['proposals:read', 'proposals:write']);
        $this->as($other)->getJson('/api/v1/me/proposals')->assertOk()->assertJsonCount(0, 'data');
        $this->as($other)->getJson('/api/v1/me/proposals/'.$proposal->id)->assertNotFound();
        $this->as($other)->postJson('/api/v1/me/proposals/'.$proposal->id.'/withdraw', ['version' => 2])->assertNotFound();
        // The client cannot apply to their own project, and a project past its cutoff takes no new proposal.
        $this->as($this->token($client))->putJson($address, $this->application())->assertForbidden();
        $late = $this->project($client, ['application_closes_at' => now()->subHour()]);
        $this->as($other)->putJson('/api/v1/projects/'.$late->id.'/proposal', $this->application())->assertStatus(409)->assertJsonPath('message', 'This proposal cannot be edited right now.');
        $this->as($other)->withHeaders(['Accept-Language' => 'ar-JO'])->putJson('/api/v1/projects/'.$late->id.'/proposal', $this->application())->assertStatus(409)
            ->assertJsonPath('message', 'لا يمكن تعديل هذا العرض حاليًا.');
        // A block between the two closes applying, as on the pages.
        $blocked = $this->member('sami@example.com', true);
        DB::table('user_blocks')->insert(['user_id' => $client->id, 'blocked_user_id' => $blocked->id, 'created_at' => now()]);
        $this->as($this->token($blocked))->putJson($address, $this->application())->assertForbidden();
        $this->assertSame([$omar->id], Proposal::query()->pluck('user_id')->all());

        // Withdrawing asks for the current version; the proposal page then shows what the API did.
        $this->as($token)->postJson('/api/v1/me/proposals/'.$proposal->id.'/withdraw', ['version' => 1])->assertStatus(409);
        $this->as($token)->postJson('/api/v1/me/proposals/'.$proposal->id.'/withdraw', ['version' => 2])->assertOk()->assertJsonPath('data.status', 'withdrawn')->assertJsonPath('data.version', 3);
        $this->as(null)->actingAs($omar, 'web')->get('/proposals/'.$proposal->id)->assertOk()->assertInertia(fn (Assert $page) => $page->where('proposal.status', 'withdrawn')->where('author', true));
        $this->assertSame(['submitted', 'withdrawn'], DB::table('proposal_events')->where('proposal_id', $proposal->id)->orderBy('id')->pluck('kind')->all());

        // Plan 027 holds here too: a member may try a demo project, marked as a sample, and the shared demo account is not told.
        $demoClient = $this->member('client-01@demo.elancer.test');
        $sample = $this->project($demoClient, ['title' => 'A sample project']);
        $this->as($token)->putJson('/api/v1/projects/'.$sample->id.'/proposal', $this->application())->assertCreated()->assertJsonPath('data.sample', true)->assertJsonPath('data.status', 'submitted');
        $this->assertSame(0, $told($demoClient));
    }

    public function test_limits_count_each_token(): void
    {
        $client = $this->member('rana@example.com');
        $omar = $this->member('omar@example.com', true);
        $address = '/api/v1/projects/'.$this->project($client)->id.'/proposal';
        $first = $this->token($omar);
        $second = $this->token($omar);

        // Twenty writes a minute for one token; a second token of the same member has its own count, and reads have theirs.
        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $this->as($first)->putJson($address, [])->assertStatus(422);
        }
        $this->as($first)->putJson($address, [])->assertStatus(429);
        $this->as($second)->putJson($address, [])->assertStatus(422);
        $this->as($first)->getJson('/api/v1/me')->assertOk();
        $this->travel(61)->seconds();
        $this->as($first)->putJson($address, [])->assertStatus(422);
    }
}

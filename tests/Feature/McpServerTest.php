<?php

namespace Tests\Feature;

use App\Http\Controllers\ProposalController;
use App\Http\Controllers\Settings\ApiTokenController;
use App\Mcp\AgentAccess;
use App\Mcp\Tools\ElancerTool;
use App\Models\Category;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

/** Plan 033: POST /mcp answers an API token by the abilities it holds and the rules the pages follow. */
class McpServerTest extends TestCase
{
    use RefreshDatabase;

    private const FREELANCER_TOOLS = ['get_account', 'search_projects', 'get_project', 'list_my_proposals', 'get_my_proposal', 'save_proposal', 'withdraw_proposal'];

    private const CLIENT_TOOLS = ['list_my_projects', 'list_received_proposals', 'get_received_proposal'];

    private function member(string $email, bool $public = false): User
    {
        $user = User::factory()->create(['name' => ucfirst(Str::before($email, '@')).' Person', 'email' => $email, 'onboarding_completed_at' => now()]);
        $user->profile()->create(['headline' => 'Laravel developer', 'bio' => 'Building reliable applications.']);
        if ($public) {
            $user->profile->syncSkillTags([Skill::query()->orderBy('id')->value('name')]);
            $user->profile->forceFill(['published_at' => now()->subDay()])->save();
        }

        return $user;
    }

    /** @param array<string, mixed> $attributes */
    private function project(User $owner, array $attributes = []): Project
    {
        $project = new Project;
        $project->forceFill(['user_id' => $owner->id, 'category_id' => Category::query()->firstOrCreate(['categoryname' => 'Development'])->id, 'title' => 'Build a booking application',
            'description' => 'A complete project brief for a booking application.', 'budget_min' => 100, 'budget_max' => 500, 'status' => 'published', 'screening_questions' => ['Which framework would you use?'],
            'published_at' => now()->subDay(), 'application_closes_at' => now()->addDays(7), ...$attributes])->save();
        $project->skills()->sync(Skill::query()->orderBy('id')->limit(2)->pluck('id'));

        return $project;
    }

    /** @param list<string> $abilities */
    private function token(User $user, array $abilities = ApiTokenController::ABILITIES): string
    {
        return $user->createToken('An agent', $abilities, now()->addDays(30))->plainTextToken;
    }

    /**
     * One JSON-RPC message, as an MCP client sends it. Each call is a separate request: nothing is carried over.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, string>  $headers
     */
    private function mcp(?string $token, string $method, array $params = [], array $headers = []): TestResponse
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        return $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18', ...$headers, ...($token === null ? [] : ['Authorization' => 'Bearer '.$token])])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params]);
    }

    /** @return list<string> */
    private function tools(string $token): array
    {
        return array_column($this->mcp($token, 'tools/list')->assertOk()->json('result.tools'), 'name');
    }

    /**
     * A tool's answer: its structured content, or its error text with the error flag.
     *
     * @param  array<string, mixed>  $arguments
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    private function tool(string $token, string $tool, array $arguments = [], array $headers = []): array
    {
        $result = $this->mcp($token, 'tools/call', ['name' => $tool, 'arguments' => (object) $arguments], $headers)->assertOk()->json('result');
        $this->assertIsArray($result);

        return ($result['isError'] ?? false) ? ['error' => $result['content'][0]['text']] : $result['structuredContent'];
    }

    public function test_the_server_needs_a_usable_token_and_lists_only_the_tools_it_may_use(): void
    {
        $omar = $this->member('omar@example.com', true);

        // No token, a made-up one and an expired one: a 401 that tells the app where to sign in.
        foreach ([null, 'made-up', '1|elancer_made-up'] as $token) {
            $this->mcp($token, 'tools/list')->assertUnauthorized()
                ->assertHeader('WWW-Authenticate', 'Bearer realm="mcp", resource_metadata="'.url('/.well-known/oauth-protected-resource/mcp').'"');
        }
        $expired = $omar->createToken('Old', ['projects:read'], now()->subMinute())->plainTextToken;
        $this->mcp($expired, 'tools/list')->assertUnauthorized();
        $this->getJson('/.well-known/oauth-protected-resource/mcp')->assertOk()->assertJsonPath('resource', url('/mcp'))->assertJsonPath('scopes_supported', ['mcp:use']);

        // Every ability: all ten tools. One ability: that one's tools and get_account.
        $this->assertSame([...self::FREELANCER_TOOLS, ...self::CLIENT_TOOLS], $this->tools($this->token($omar)));
        $this->assertSame(['get_account', 'search_projects', 'get_project'], $this->tools($this->token($omar, ['projects:read'])));
        $this->assertSame(['get_account', ...self::CLIENT_TOOLS], $this->tools($this->token($omar, ['hiring:read'])));

        // A tool the token may not use is not there to call.
        $readOnly = $this->token($omar, ['projects:read']);
        $this->mcp($readOnly, 'tools/call', ['name' => 'list_my_proposals', 'arguments' => (object) []])->assertStatus(400)
            ->assertJsonPath('error.code', -32602)->assertJsonPath('error.message', 'Tool [list_my_proposals] not found.');
        $account = $this->tool($readOnly, 'get_account');
        $this->assertSame(['id' => $omar->id, 'name' => 'Omar Person', 'email' => 'omar@example.com', 'status' => 'active', 'can_apply' => true, 'profile_published' => true],
            array_intersect_key($account, array_flip(['id', 'name', 'email', 'status', 'can_apply', 'profile_published'])));
        $this->assertSame(['kind' => 'api_token', 'name' => 'An agent', 'abilities' => ['projects:read']], array_intersect_key($account['connection'], array_flip(['kind', 'name', 'abilities'])));
    }

    public function test_without_the_signing_keys_a_foreign_token_is_refused_and_api_tokens_still_work(): void
    {
        // A host that has not been given Passport's keys yet: Connect cannot work, the server still answers.
        config(['passport.public_key' => null, 'passport.private_key' => null]);
        Passport::loadKeysFrom(storage_path('framework/testing/no-passport-keys'));
        try {
            $this->mcp('header.payload.signature', 'tools/list')->assertUnauthorized();
            $this->mcp($this->token($this->member('omar@example.com', true)), 'tools/list')->assertOk();
        } finally {
            Passport::$keyPath = null;
        }
    }

    public function test_the_account_behind_a_token_must_still_be_allowed(): void
    {
        $demo = $this->member('freelancer-01@demo.elancer.test', true);
        $this->mcp($this->token($demo), 'tools/list')->assertForbidden()->assertJsonPath('message', 'This account cannot use the API.');

        // A suspended member reads their account and public projects, and nothing of their own work.
        $sami = $this->member('sami@example.com', true);
        $token = $this->token($sami);
        $sami->forceFill(['status' => 'suspended'])->save();
        $this->assertSame('suspended', $this->tool($token, 'get_account')['status']);
        $this->assertSame(['error' => 'This account may not do that.'], $this->tool($token, 'list_my_proposals'));
        $this->assertSame(['error' => 'لا يستطيع هذا الحساب فعل ذلك.'], $this->tool($token, 'list_my_proposals', [], ['Accept-Language' => 'ar']));

        $sami->forceFill(['status' => 'deactivated'])->save();
        $this->mcp($token, 'tools/list')->assertForbidden();
    }

    public function test_a_freelancer_searches_applies_and_withdraws_by_the_rules_of_the_pages(): void
    {
        $client = $this->member('rana@example.com');
        $omar = $this->member('omar@example.com', true);
        $project = $this->project($client);
        $this->project($client, ['title' => 'A private draft', 'status' => 'draft', 'published_at' => null]);
        $token = $this->token($omar);

        $found = $this->tool($token, 'search_projects', ['q' => 'booking']);
        $this->assertSame([$project->id], array_column($found['data'], 'id'));
        $this->assertSame(1, $found['total']);
        $this->assertSame(['Which framework would you use?'], $this->tool($token, 'get_project', ['project_id' => $project->id])['screening_questions']);
        $this->assertSame(['error' => 'Not found, or not visible to this account.'], $this->tool($token, 'get_project', ['project_id' => $project->id + 1]));

        // A draft keeps what the agent left out; a stale version is refused with the current one named.
        $draft = $this->tool($token, 'save_proposal', ['project_id' => $project->id, 'action' => 'save', 'version' => 0, 'price' => 300, 'message' => 'A first idea for this booking work.', 'answers' => ['Laravel']]);
        $this->assertTrue($draft['created']);
        $this->assertSame('draft', $draft['status']);
        $again = $this->tool($token, 'save_proposal', ['project_id' => $project->id, 'action' => 'save', 'version' => 1, 'duration_days' => 9]);
        $this->assertSame(['300.00', 9, 'A first idea for this booking work.', ['Laravel']], [$again['draft']['price'], $again['draft']['duration_days'], $again['draft']['message'], $again['draft']['answers']]);
        $this->assertSame(['error' => 'This proposal changed since the version you sent (you sent 1, it is now 2). Read it again before saving.'],
            $this->tool($token, 'save_proposal', ['project_id' => $project->id, 'action' => 'save', 'version' => 1]));

        // Submitting asks what the page asks.
        $refused = $this->tool($token, 'save_proposal', ['project_id' => $project->id, 'action' => 'submit', 'version' => 2, 'message' => 'Too short']);
        $this->assertStringContainsString('message', $refused['error']);
        $sent = $this->tool($token, 'save_proposal', ['project_id' => $project->id, 'action' => 'submit', 'version' => 2, 'message' => 'I have built several booking applications like this one.']);
        $this->assertSame(['submitted', 3, '300.00'], [$sent['status'], $sent['version'], $sent['content']['price']]);
        $this->assertSame(1, $client->notifications()->count());
        $this->assertSame([$sent['id']], array_column($this->tool($token, 'list_my_proposals')['data'], 'id'));
        $this->assertSame(3, $this->tool($token, 'get_my_proposal', ['proposal_id' => $sent['id']])['version']);

        // Someone else's proposal does not exist for this caller, and their own project cannot be applied to.
        $lina = $this->member('lina@example.com', true);
        $this->assertSame(['error' => 'Not found, or not visible to this account.'], $this->tool($this->token($lina), 'get_my_proposal', ['proposal_id' => $sent['id']]));
        $this->assertArrayHasKey('error', $this->tool($this->token($client), 'save_proposal', ['project_id' => $project->id, 'action' => 'save', 'version' => 0]));

        $withdrawn = $this->tool($token, 'withdraw_proposal', ['proposal_id' => $sent['id'], 'version' => 3]);
        $this->assertSame(['withdrawn', 4], [$withdrawn['status'], $withdrawn['version']]);
        $this->assertSame(['error' => 'This changed in the meantime. Read it again before trying.'], $this->tool($token, 'withdraw_proposal', ['proposal_id' => $sent['id'], 'version' => 3]));
    }

    public function test_a_client_reads_their_projects_and_the_proposals_they_received_and_changes_nothing(): void
    {
        $client = $this->member('rana@example.com');
        $omar = $this->member('omar@example.com', true);
        $project = $this->project($client);
        $this->project($client, ['title' => 'A private draft', 'status' => 'draft', 'published_at' => null]);
        $theirs = $this->project($this->member('hadi@example.com'), ['title' => 'Another client']);
        $sent = $this->tool($this->token($omar), 'save_proposal', ['project_id' => $project->id, 'action' => 'submit', 'version' => 0, 'price' => 320, 'duration_days' => 10,
            'message' => 'I have built several booking applications like this one.', 'answers' => ['Laravel'], 'samples' => [['label' => 'Bookings', 'url' => 'https://example.com/bookings']]]);
        Proposal::query()->whereKey($sent['id'])->update(['client_note' => 'Strong portfolio']);
        $token = $this->token($client, ['hiring:read']);

        $mine = $this->tool($token, 'list_my_projects');
        $this->assertSame(['all' => 2, 'draft' => 1, 'open' => 1, 'closed' => 0, 'hired' => 0, 'trash' => 0, 'waiting' => 1], $mine['counts']);
        $rows = collect($mine['data'])->keyBy('title');
        $this->assertSame([$project->id, 1, 1, route('jobs.show', $project)], [$rows['Build a booking application']['id'], $rows['Build a booking application']['proposals_received'],
            $rows['Build a booking application']['waiting_for_reply'], $rows['Build a booking application']['url']]);
        $this->assertSame([0, 0, 'draft'], [$rows['A private draft']['proposals_received'], $rows['A private draft']['waiting_for_reply'], $rows['A private draft']['status']]);
        $this->assertSame([], $this->tool($token, 'list_my_projects', ['list' => 'hired'])['data']);

        $received = $this->tool($token, 'list_received_proposals', ['project_id' => $project->id]);
        $this->assertSame([[$sent['id'], '320.00', 10, 'Omar Person', 'Laravel developer']], array_map(fn (array $row) => [$row['id'], $row['price'], $row['duration_days'], $row['freelancer']['name'], $row['freelancer']['headline']], $received['data']));
        $full = $this->tool($token, 'get_received_proposal', ['proposal_id' => $sent['id']]);
        $this->assertSame([['question' => 'Which framework would you use?', 'answer' => 'Laravel']], $full['answers']);
        $this->assertSame(['Strong portfolio', 'received', false, []], [$full['client_note'], $full['organization'], $full['conversation'], $full['offers']]);
        $this->assertSame([['label' => 'Bookings', 'url' => 'https://example.com/bookings']], $full['samples']);

        // Another client's project and proposals do not exist here; reading changed nothing.
        $this->assertSame(['error' => 'Not found, or not visible to this account.'], $this->tool($token, 'list_received_proposals', ['project_id' => $theirs->id]));
        $this->assertSame(['error' => 'Not found, or not visible to this account.'], $this->tool($this->token($this->member('hadi2@example.com'), ['hiring:read']), 'get_received_proposal', ['proposal_id' => $sent['id']]));
        $after = Proposal::query()->findOrFail($sent['id']);
        $this->assertSame([1, 'received', 'submitted'], [$after->version, $after->organization, $after->status]);
        $this->assertSame(1, $client->notifications()->count());
        $this->assertSame(0, $omar->notifications()->count());
    }

    public function test_a_member_trying_a_demo_project_stays_hidden_from_the_demo_client(): void
    {
        $demoClient = $this->member('client-01@demo.elancer.test');
        $sample = $this->project($demoClient, ['title' => 'A sample project']);
        $omar = $this->member('omar@example.com', true);

        $sent = $this->tool($this->token($omar), 'save_proposal', ['project_id' => $sample->id, 'action' => 'submit', 'version' => 0, 'price' => 300, 'duration_days' => 7,
            'message' => 'I have built several booking applications like this one.', 'answers' => ['Laravel']]);
        $this->assertSame([true, 'submitted'], [$sent['sample'], $sent['status']]);
        $this->assertSame(0, $demoClient->notifications()->count());
        // The demo client never holds a token, and the rule the pages use keeps the application with the member.
        $this->assertFalse(ProposalController::clientReads($demoClient->id, Proposal::query()->findOrFail($sent['id'])));
        $this->mcp($this->token($demoClient, ['hiring:read']), 'tools/list')->assertForbidden();
    }

    public function test_writes_count_toward_a_limit_per_token(): void
    {
        $omar = $this->member('omar@example.com', true);
        $project = $this->project($this->member('rana@example.com'));
        $token = $this->token($omar);
        $omar->withAccessToken($omar->tokens()->first());
        for ($i = 0; $i < ElancerTool::WRITES_PER_MINUTE; $i++) {
            RateLimiter::hit('mcp-write:'.AgentAccess::key($omar), 60);
        }

        $this->assertSame(['error' => 'Too many changes in a minute. Try again shortly.'], $this->tool($token, 'save_proposal', ['project_id' => $project->id, 'action' => 'save', 'version' => 0]));
        $this->assertSame(0, Proposal::query()->count());
        // Another token of the same member has its own allowance, and reading is not a write.
        $this->assertSame('draft', $this->tool($this->token($omar), 'save_proposal', ['project_id' => $project->id, 'action' => 'save', 'version' => 0])['status']);
        $this->assertSame('Omar Person', $this->tool($token, 'get_account')['name']);
    }
}

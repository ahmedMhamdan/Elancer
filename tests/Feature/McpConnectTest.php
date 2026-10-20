<?php

namespace Tests\Feature;

use App\Mcp\AgentAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use phpseclib4\Crypt\RSA;
use Tests\TestCase;

/** Plan 033: an AI app connects through OAuth 2.1 with PKCE, the member approves, and the app calls /mcp. */
class McpConnectTest extends TestCase
{
    use RefreshDatabase;

    private const REDIRECT = 'https://claude.ai/api/mcp/auth_callback';

    /** @var array{private: string, public: string}|null One key pair for the whole run: the host's keys are not in the repository. */
    private static ?array $keys = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (self::$keys === null) {
            $key = RSA::createKey(2048);
            self::$keys = ['private' => (string) $key, 'public' => (string) $key->getPublicKey()];
        }
        config(['passport.private_key' => self::$keys['private'], 'passport.public_key' => self::$keys['public']]);
    }

    private function member(string $email): User
    {
        $user = User::factory()->create(['name' => ucfirst(Str::before($email, '@')).' Person', 'email' => $email, 'onboarding_completed_at' => now()]);
        $user->profile()->create(['headline' => 'Laravel developer', 'bio' => 'Building reliable applications.']);

        return $user;
    }

    /** @return array<string, int> */
    private function recent(): array
    {
        return ['auth.password_confirmed_at' => now()->unix()];
    }

    private function register(string $name = 'Claude', string $redirect = self::REDIRECT): string
    {
        return (string) $this->postJson('/oauth/register', ['client_name' => $name, 'redirect_uris' => [$redirect]])->assertCreated()->json('client_id');
    }

    /** @return array{url: string, verifier: string} */
    private function request(string $client, string $redirect = self::REDIRECT): array
    {
        $verifier = Str::random(64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return ['verifier' => $verifier, 'url' => '/oauth/authorize?'.http_build_query(['client_id' => $client, 'redirect_uri' => $redirect, 'response_type' => 'code',
            'scope' => 'mcp:use', 'state' => 'state-1', 'code_challenge' => $challenge, 'code_challenge_method' => 'S256'])];
    }

    /** The approval page's form, as the member's browser posts it. */
    private function approve(TestResponse $page): TestResponse
    {
        $form = $page->viewData('page')['props']['form'];

        return $this->post($form['action'], ['state' => $form['state'], 'client_id' => $form['clientId'], 'auth_token' => $form['authToken']]);
    }

    private function code(TestResponse $redirect): string
    {
        $location = (string) $redirect->headers->get('Location');
        $this->assertStringStartsWith(self::REDIRECT.'?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('state-1', $query['state'] ?? null);

        return (string) $query['code'];
    }

    /** @param array<string, string> $fields */
    private function exchange(array $fields): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->post('/oauth/token', $fields, ['Accept' => 'application/json']);
    }

    /** @param array<string, mixed> $params */
    private function mcp(string $token, string $method, array $params = []): TestResponse
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        return $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18', 'Authorization' => 'Bearer '.$token])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params]);
    }

    /** A connected app as the database holds one: a registered client and a token that still works. */
    private function connectedApp(User $user, string $name): Client
    {
        $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient($name, ['https://example.com/'.Str::slug($name)], false);
        $token = Str::random(80);
        DB::table('oauth_access_tokens')->insert(['id' => $token, 'user_id' => $user->id, 'client_id' => $client->getKey(), 'scopes' => json_encode(['mcp:use']),
            'revoked' => false, 'created_at' => now(), 'updated_at' => now(), 'expires_at' => now()->addHour()]);
        DB::table('oauth_refresh_tokens')->insert(['id' => Str::random(80), 'access_token_id' => $token, 'revoked' => false, 'expires_at' => now()->addDays(30)]);

        return $client;
    }

    public function test_an_app_finds_the_sign_in_connects_calls_the_server_renews_and_is_disconnected(): void
    {
        $omar = $this->member('omar@example.com');

        $this->getJson('/.well-known/oauth-authorization-server')->assertOk()
            ->assertJsonPath('authorization_endpoint', url('/oauth/authorize'))->assertJsonPath('token_endpoint', url('/oauth/token'))
            ->assertJsonPath('registration_endpoint', url('/oauth/register'))->assertJsonPath('code_challenge_methods_supported', ['S256']);
        $client = $this->register();
        $request = $this->request($client);

        // A guest signs in first; a sign-in that is not recent is confirmed again, as on the tokens page.
        $this->get($request['url'])->assertRedirect('/login');
        $this->actingAs($omar)->get($request['url'])->assertRedirect(route('password.confirm'));
        $page = $this->withSession($this->recent())->get($request['url'])->assertOk()->assertInertia(fn (Assert $page) => $page->component('oauth/authorize')
            ->where('app', ['name' => 'Claude', 'returnsTo' => 'claude.ai'])->where('account', ['name' => 'Omar Person', 'email' => 'omar@example.com'])
            ->where('refusal', null)->where('form.action', url('/oauth/authorize'))->where('form.clientId', $client));
        $code = $this->code($this->approve($page));

        // The code is worth nothing without the verifier only the app holds.
        $fields = ['grant_type' => 'authorization_code', 'client_id' => $client, 'redirect_uri' => self::REDIRECT, 'code' => $code];
        $this->exchange([...$fields, 'code_verifier' => Str::random(64)])->assertStatus(400);
        $tokens = $this->exchange([...$fields, 'code_verifier' => $request['verifier']])->assertOk()->assertJsonPath('token_type', 'Bearer')->json();
        // An hour, give or take the second the request took.
        $this->assertEqualsWithDelta(3600, $tokens['expires_in'], 5);
        $this->exchange([...$fields, 'code_verifier' => $request['verifier']])->assertStatus(400);

        // A connected app may use every tool.
        $this->assertCount(10, $this->mcp($tokens['access_token'], 'tools/list')->assertOk()->json('result.tools'));
        $this->mcp($tokens['access_token'], 'tools/call', ['name' => 'get_account', 'arguments' => (object) []])->assertOk()
            ->assertJsonPath('result.structuredContent.connection', ['kind' => 'connected_app', 'name' => 'Claude', 'may_use' => 'every tool']);

        // Renewing replaces the access token; the old one stops working.
        $renewed = $this->exchange(['grant_type' => 'refresh_token', 'refresh_token' => $tokens['refresh_token'], 'client_id' => $client])->assertOk()->json();
        $this->mcp($tokens['access_token'], 'tools/list')->assertUnauthorized();
        $this->mcp($renewed['access_token'], 'tools/list')->assertOk();

        // The member sees the app on the tokens page and can disconnect it there; it is refused at once.
        $this->actingAs($omar, 'web')->withSession($this->recent())->get('/settings/tokens')->assertOk()->assertInertia(fn (Assert $page) => $page->has('connections', 1)
            ->where('connections.0.id', $client)->where('connections.0.name', 'Claude')->where('connections.0.returnsTo', 'claude.ai')->where('connectionLimit', 10)->where('mcpEndpoint', url('/mcp')));
        // Asking again while connected needs no second approval.
        $this->assertNotSame('', $this->code($this->get($this->request($client)['url'])));
        $this->withSession($this->recent())->delete('/settings/connections/'.$client)->assertRedirect();
        $this->mcp($renewed['access_token'], 'tools/list')->assertUnauthorized();
        $this->exchange(['grant_type' => 'refresh_token', 'refresh_token' => $renewed['refresh_token'], 'client_id' => $client])->assertStatus(400);
        $this->actingAs($omar, 'web')->withSession($this->recent())->delete('/settings/connections/'.$client)->assertNotFound();
    }

    public function test_demo_accounts_accounts_that_may_not_act_and_full_accounts_cannot_approve(): void
    {
        $demo = $this->member('client-01@demo.elancer.test');
        $this->member('freelancer-01@demo.elancer.test');
        $client = $this->register();
        $request = $this->request($client);

        // The prepared account, entered with its own credentials: the page says why and the server refuses.
        $page = $this->actingAs($demo)->withSession($this->recent())->get($request['url'])->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('refusal', 'Demo accounts cannot connect AI agents. Create your own account to connect one.'));
        $this->approve($page)->assertForbidden();
        $this->post('/logout');

        // Through the demo button: approving is closed, turning the app down still answers it.
        $this->post('/demo/client')->assertRedirect('/dashboard');
        $page = $this->withSession($this->recent())->get($request['url'])->assertOk()->assertInertia(fn (Assert $page) => $page->where('refusal', 'Demo accounts cannot connect AI agents. Create your own account to connect one.'));
        $this->approve($page)->assertStatus(303);
        $page = $this->withSession($this->recent())->get($request['url'])->assertOk();
        $form = $page->viewData('page')['props']['form'];
        $denied = (string) $this->delete('/oauth/authorize', ['state' => $form['state'], 'client_id' => $form['clientId'], 'auth_token' => $form['authToken']])->headers->get('Location');
        $this->assertStringStartsWith(self::REDIRECT.'?', $denied);
        $this->assertStringContainsString('error=access_denied', $denied);
        $this->post('/logout');

        // A suspended member keeps their pages and connects nothing new.
        $sami = $this->member('sami@example.com');
        $sami->forceFill(['status' => 'suspended'])->save();
        $page = $this->actingAs($sami)->withSession($this->recent())->get($request['url'])->assertInertia(fn (Assert $page) => $page->where('refusal', 'This account cannot connect AI agents right now.'));
        $this->approve($page)->assertForbidden();

        // Ten connected apps at most; one already connected may still renew its approval.
        $lina = $this->member('lina@example.com');
        $apps = collect(range(1, AgentAccess::CONNECTIONS))->map(fn (int $n) => $this->connectedApp($lina, 'Agent '.$n));
        $page = $this->actingAs($lina)->withSession($this->recent())->get($request['url'])
            ->assertInertia(fn (Assert $page) => $page->where('refusal', 'This account already has the most connected apps it can hold. Disconnect one first.'));
        $this->approve($page)->assertForbidden();
        $this->assertNull(AgentAccess::refusal($lina, (string) $apps->first()->getKey()));

        $this->assertSame(0, DB::table('oauth_auth_codes')->count());
        $this->assertSame(AgentAccess::CONNECTIONS, DB::table('oauth_access_tokens')->count());
    }

    public function test_an_app_needs_a_registered_address_and_unused_apps_and_deleted_accounts_leave_nothing(): void
    {
        // Registration takes a web address or one of the two editors' schemes, nothing else.
        $this->postJson('/oauth/register', ['client_name' => 'Cursor', 'redirect_uris' => ['cursor://anysphere.cursor-retrieval/oauth/callback']])->assertCreated();
        $this->postJson('/oauth/register', ['client_name' => 'Odd', 'redirect_uris' => ['javascript://alert']])->assertStatus(400)->assertJsonPath('error', 'invalid_redirect_uri');
        $client = $this->register();
        $omar = $this->member('omar@example.com');
        // Another address than the registered one is refused before any page is shown.
        $this->actingAs($omar)->withSession($this->recent())->get($this->request($client, 'https://evil.example/callback')['url'])->assertClientError();
        $this->assertSame(0, DB::table('oauth_auth_codes')->count());

        $request = $this->request($client);
        $code = $this->code($this->approve($this->withSession($this->recent())->get($request['url'])));
        $tokens = $this->exchange(['grant_type' => 'authorization_code', 'client_id' => $client, 'redirect_uri' => self::REDIRECT, 'code' => $code, 'code_verifier' => $request['verifier']])->json();
        $this->mcp($tokens['access_token'], 'tools/list')->assertOk();

        // Deleting the account removes its tokens and codes; the app's token is refused at once.
        $this->actingAs($omar, 'web')->delete(route('profile.destroy'), ['password' => 'password'])->assertSessionHasNoErrors();
        $this->assertSame([0, 0, 0], [DB::table('oauth_access_tokens')->count(), DB::table('oauth_refresh_tokens')->count(), DB::table('oauth_auth_codes')->count()]);
        $this->mcp($tokens['access_token'], 'tools/list')->assertUnauthorized();

        // A day later the apps nobody holds a connection to are removed; one still connected stays.
        $this->connectedApp($this->member('lina@example.com'), 'Agent 1');
        $this->travel(2)->days();
        $this->artisan('oauth:prune')->assertSuccessful();
        $this->assertSame(['Agent 1'], Client::query()->pluck('name')->all());
    }
}

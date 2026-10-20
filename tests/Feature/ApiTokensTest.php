<?php

namespace Tests\Feature;

use App\Actions\Demo\DemoBaseline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Plan 028 (plan 003 section 6): a member's own API tokens, made behind a recent sign-in, shown once, expiring and revocable. */
class ApiTokensTest extends TestCase
{
    use RefreshDatabase;

    private const RECENT = ['auth.password_confirmed_at' => 0];

    private function member(string $email): User
    {
        $user = User::factory()->create(['name' => ucfirst(Str::before($email, '@')).' Person', 'email' => $email, 'onboarding_completed_at' => now()]);
        $user->profile()->create(['headline' => 'Laravel developer', 'bio' => 'Building reliable applications.']);

        return $user;
    }

    /** @return array<string, int> */
    private function recent(): array
    {
        // The test clock, so a sign-in stays recent after the test moves time forward.
        return ['auth.password_confirmed_at' => now()->unix()];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function wanted(array $overrides = []): array
    {
        return ['name' => '  Nightly export  ', 'abilities' => ['proposals:read', 'projects:read'], 'expires_in_days' => 30, ...$overrides];
    }

    public function test_tokens_are_managed_behind_a_recent_sign_in_and_the_secret_is_shown_once(): void
    {
        $omar = $this->member('omar@example.com');
        $lina = $this->member('lina@example.com');

        // A guest, and a member whose sign-in is not recent, reach nothing.
        $this->get('/settings/tokens')->assertRedirect('/login');
        $this->postJson('/settings/tokens', $this->wanted())->assertUnauthorized();
        $this->actingAs($omar)->withSession(self::RECENT)->get('/settings/tokens')->assertRedirect(route('password.confirm'));
        $this->postJson('/settings/tokens', $this->wanted())->assertStatus(423);
        $this->deleteJson('/settings/tokens/1')->assertStatus(423);
        $this->assertSame(0, DB::table('personal_access_tokens')->count());

        $this->withSession($this->recent())->get('/settings/tokens')->assertOk()->assertInertia(fn (Assert $page) => $page->component('settings/tokens')->has('tokens', 0)
            ->where('abilities', ['projects:read', 'proposals:read', 'proposals:write', 'hiring:read'])->where('expiryDays', [7, 30, 60, 90])->where('defaultExpiryDays', 30)
            ->where('limit', 10)->where('allowed', true)->where('endpoint', url('/api/v1'))->where('mcpEndpoint', url('/mcp'))->has('connections', 0));

        // A name, at least one known ability and one of the four expiries; nothing longer than ninety days and nothing without an end.
        foreach ([['name' => ''], ['name' => str_repeat('n', 61)], ['abilities' => []], ['abilities' => ['projects:read', 'admin']], ['abilities' => ['*']],
            ['expires_in_days' => 365], ['expires_in_days' => 0], ['expires_in_days' => null]] as $invalid) {
            $this->postJson('/settings/tokens', $this->wanted($invalid))->assertStatus(422);
        }
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
        // The page takes ten requests a minute; the checks above used most of them.
        $this->travel(61)->seconds();

        // The secret is the answer to this one request, marked not to be stored.
        $created = $this->postJson('/settings/tokens', $this->wanted())->assertCreated()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('item.name', 'Nightly export')->assertJsonPath('item.abilities', ['projects:read', 'proposals:read'])->assertJsonPath('item.expired', false)
            ->assertJsonPath('item.last_used_at', null);
        $secret = (string) $created->json('token');
        $this->assertMatchesRegularExpression('/^\d+\|elancer_[A-Za-z0-9]{40,}$/', $secret);
        $row = DB::table('personal_access_tokens')->first();
        $this->assertSame([$omar->id, hash('sha256', Str::after($secret, '|'))], [(int) $row->tokenable_id, $row->token]);
        $this->assertEqualsWithDelta(now()->addDays(30)->getTimestamp(), strtotime((string) $row->expires_at), 5);
        // Nowhere else: not in the database, not in the session, not in the page that lists the token afterwards.
        $this->assertStringNotContainsString(Str::after($secret, '|'), (string) json_encode((array) $row));
        $this->assertStringNotContainsString(Str::after($secret, '|'), (string) json_encode(session()->all()));
        $page = $this->get('/settings/tokens')->assertOk()->assertInertia(fn (Assert $page) => $page->has('tokens', 1)->where('tokens.0.name', 'Nightly export')
            ->where('tokens.0.abilities', ['projects:read', 'proposals:read'])->where('tokens.0.expired', false)->missing('tokens.0.token'));
        $this->assertStringNotContainsString(Str::after($secret, '|'), (string) $page->getContent());

        // It works for what it was given, and the page then says when it was last used and when it has expired.
        $this->app['auth']->forgetGuards();
        $this->withToken($secret)->getJson('/api/v1/projects')->assertOk();
        $this->getJson('/api/v1/me/proposals')->assertOk();
        $this->putJson('/api/v1/projects/1/proposal', [])->assertForbidden();
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->actingAs($omar, 'web')->withSession($this->recent())->get('/settings/tokens')->assertInertia(fn (Assert $page) => $page->where('tokens.0.last_used_at', fn (?string $used): bool => $used !== null));

        // Ten tokens at most, expired ones included.
        for ($number = 2; $number <= 10; $number++) {
            $this->travel(7)->seconds();
            $this->postJson('/settings/tokens', $this->wanted(['name' => 'Token '.$number, 'expires_in_days' => 7]))->assertCreated();
        }
        $this->travel(7)->seconds();
        $this->postJson('/settings/tokens', $this->wanted())->assertStatus(422)->assertJsonValidationErrors('name');
        $this->assertSame(10, DB::table('personal_access_tokens')->count());
        $this->travel(8)->days();
        $this->withSession($this->recent())->get('/settings/tokens')->assertInertia(fn (Assert $page) => $page->where('tokens.0.expired', true)->where('tokens.9.expired', false));
        $this->postJson('/settings/tokens', $this->wanted())->assertStatus(422);

        // Revoking is the owner's alone and ends the token at once.
        $first = (int) Str::before($secret, '|');
        $this->actingAs($lina, 'web')->withSession($this->recent())->delete('/settings/tokens/'.$first)->assertNotFound();
        $this->actingAs($omar, 'web')->withSession($this->recent())->delete('/settings/tokens/'.$first)->assertRedirect();
        $this->assertSame(9, DB::table('personal_access_tokens')->count());
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->withToken($secret)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_a_demo_account_and_an_account_that_may_not_act_never_issue_a_token(): void
    {
        $demo = $this->member('client-01@demo.elancer.test');
        $this->member('freelancer-01@demo.elancer.test');

        // The prepared account entered with its own credentials (plan 003 section 10).
        $this->actingAs($demo)->withSession($this->recent())->get('/settings/tokens')->assertOk()->assertInertia(fn (Assert $page) => $page->where('allowed', false));
        $this->postJson('/settings/tokens', $this->wanted())->assertForbidden()->assertJsonPath('message', 'This account cannot create API tokens.');
        $this->post('/logout');

        // And through the demo button, where settings are closed altogether, with or without a starting point.
        foreach ([false, true] as $acting) {
            if ($acting) {
                DemoBaseline::save(null);
            }
            $this->post('/demo/client')->assertRedirect('/dashboard');
            $this->withSession($this->recent())->get('/settings/tokens')->assertRedirect('/dashboard');
            $this->postJson('/settings/tokens', $this->wanted())->assertForbidden();
            $this->post('/logout');
        }

        // A suspended member keeps their pages and gets no new token; an unverified one does not reach the page.
        $suspended = $this->member('sami@example.com');
        $suspended->forceFill(['status' => 'suspended'])->save();
        $this->actingAs($suspended)->withSession($this->recent())->postJson('/settings/tokens', $this->wanted())->assertForbidden();
        $unverified = $this->member('nour@example.com');
        $unverified->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($unverified)->withSession($this->recent())->postJson('/settings/tokens', $this->wanted())->assertForbidden();
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_an_account_that_is_deleted_takes_its_tokens_with_it(): void
    {
        $omar = $this->member('omar@example.com');
        $lina = $this->member('lina@example.com');
        $secret = $omar->createToken('A script', ['projects:read'], now()->addDays(30))->plainTextToken;
        $lina->createToken('Hers', ['projects:read'], now()->addDays(30));

        $this->actingAs($omar)->delete(route('profile.destroy'), ['password' => 'password'])->assertSessionHasNoErrors()->assertRedirect(route('home'));
        $this->assertNull($omar->fresh());
        $this->assertSame([$lina->id], DB::table('personal_access_tokens')->pluck('tokenable_id')->map(fn ($id): int => (int) $id)->all());
        $this->app['auth']->forgetGuards();
        $this->withToken($secret)->getJson('/api/v1/me')->assertUnauthorized();
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Models\SocialIdentity;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Passkeys\Passkey;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as ProviderUser;
use Mockery;
use Tests\TestCase;

class SocialAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['google', 'github'] as $provider) {
            config(["services.$provider.client_id" => 'test-client', "services.$provider.client_secret" => 'test-secret', "services.$provider.redirect" => url("/auth/$provider/callback")]);
        }
    }

    private function remote(string $provider = 'google', string $subject = 'stable-123', ?string $email = 'member@gmail.com', bool $verified = true): void
    {
        $remote = (new ProviderUser)->setRaw(['sub' => $subject, 'email_verified' => $verified])->map(['id' => $subject, 'email' => $email, 'name' => 'Social Member', 'nickname' => 'member']);
        $driver = Mockery::mock(AbstractProvider::class);
        $driver->shouldReceive('setHttpClient')->andReturnSelf();
        $driver->shouldReceive('user')->once()->andReturn($remote);
        Socialite::shouldReceive('driver')->with($provider)->andReturn($driver);
    }

    /** @return array<string, mixed> */
    private function flow(string $provider = 'google', string $purpose = 'login', ?User $user = null): array
    {
        return ['oauth.flow' => ['provider' => $provider, 'purpose' => $purpose, 'user_id' => $user?->id, 'started_at' => time()]];
    }

    private function identity(User $user, string $provider = 'google', string $subject = 'stable-123'): SocialIdentity
    {
        $identity = new SocialIdentity;
        $identity->forceFill(['user_id' => $user->id, 'provider' => $provider, 'subject' => $subject])->save();

        return $identity;
    }

    public function test_redirect_is_stateful_and_unknown_unconfigured_or_invalid_state_cannot_authenticate(): void
    {
        $response = $this->get('/auth/google/redirect')->assertRedirect();
        $query = [];
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertNotEmpty($query['state']);
        $this->assertSame(url('/auth/google/callback'), $query['redirect_uri']);
        $this->get('/auth/google/callback?state=wrong&code=fake')->assertRedirect('/login')->assertSessionHasErrors('social');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->get('/auth/unknown/redirect')->assertNotFound();
        config(['services.github.client_secret' => null]);
        $this->from('/login')->get('/auth/github/redirect')->assertRedirect('/login')->assertSessionHasErrors('social');
    }

    public function test_new_social_member_is_passwordless_verified_and_keeps_locale_without_tokens(): void
    {
        $this->remote();
        $this->withSession([...$this->flow(), 'locale' => 'ar'])->get('/auth/google/callback')->assertRedirect('/dashboard');
        $user = User::query()->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->password);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertSame('ar', $user->locale);
        $this->assertFalse($user->isAdministrator());
        $this->assertDatabaseHas('social_identities', ['provider' => 'google', 'subject' => 'stable-123', 'user_id' => $user->id]);
        $this->assertArrayNotHasKey('token', SocialIdentity::query()->firstOrFail()->getAttributes());
        $this->get('/dashboard')->assertRedirect('/onboarding');
    }

    public function test_email_collision_never_links_existing_account(): void
    {
        $existing = User::factory()->create(['email' => 'member@gmail.com']);
        $this->remote();
        $this->withSession($this->flow())->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHasErrors('social');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('social_identities', 0);
        $this->assertNotNull($existing->fresh()->password);
    }

    public function test_missing_or_unreliable_email_requires_normal_email_verification(): void
    {
        Notification::fake();
        $this->remote('github', 'github-1', null);
        $this->withSession($this->flow('github'))->get('/auth/github/callback')->assertRedirect('/auth/complete');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->get('/auth/complete')->assertInertia(fn (Assert $page) => $page->component('auth/social-complete')->where('name', 'Social Member'));
        $this->post('/auth/complete', ['name' => 'Member', 'email' => 'chosen@example.test'])->assertRedirect('/dashboard');
        $user = User::query()->firstOrFail();
        $this->assertNull($user->password);
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->get('/dashboard')->assertRedirect(route('verification.notice'));
    }

    public function test_linked_subject_not_email_controls_login_and_second_factor_is_not_bypassed(): void
    {
        $user = User::factory()->withTwoFactor()->create(['password' => null, 'is_super_admin' => true, 'two_factor_secret' => encrypt(app(TwoFactorAuthenticationProvider::class)->generateSecretKey())]);
        $this->identity($user);
        $this->remote('google', 'stable-123', 'changed@gmail.com');
        $this->withSession([...$this->flow(), 'admin.two_factor_proof' => 'old', 'auth.password_confirmed_at' => time()])->get('/auth/google/callback')->assertRedirect(route('two-factor.login'))->assertSessionHas('login.id', $user->id)->assertSessionMissing('admin.two_factor_proof')->assertSessionMissing('auth.password_confirmed_at');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
        $this->assertNotSame('changed@gmail.com', $user->fresh()->email);
        $this->post('/two-factor-challenge', ['code' => 'wrong'])->assertSessionHasErrors();
        $this->assertGuest();
        $this->post('/two-factor-challenge', ['recovery_code' => $user->recoveryCodes()[0]])->assertRedirect('/dashboard')->assertSessionHas('admin.two_factor_proof');
        $this->assertAuthenticatedAs($user);
    }

    public function test_expired_cancelled_and_cross_session_flows_fail_without_authentication(): void
    {
        $this->get('/auth/google/callback?code=fake')->assertSessionHasErrors('social');
        $flow = $this->flow();
        $flow['oauth.flow']['started_at'] -= 601;
        $this->withSession($flow)->get('/auth/google/callback?code=fake')->assertSessionHasErrors('social');
        $this->withSession($this->flow())->get('/auth/google/callback?error=access_denied')->assertSessionHasErrors('social');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_sign_in_started_on_another_local_address_restarts_on_the_callback_address(): void
    {
        $restart = url('/auth/google/redirect').'?canonical=1';
        $this->get('http://127.0.0.1:8123/auth/google/redirect')->assertRedirect($restart)->assertSessionMissing('oauth.flow');
        $this->get('/auth/google/redirect?canonical=1')->assertSessionHas('oauth.flow.purpose', 'login');
    }

    public function test_confirmation_started_on_another_local_address_names_the_callback_address(): void
    {
        $user = User::factory()->create(['password' => null]);
        $this->identity($user);
        $this->actingAs($user)->from('http://127.0.0.1:8123/user/confirm-password')->post('http://127.0.0.1:8123/settings/social/google/confirm')->assertRedirect('http://127.0.0.1:8123/user/confirm-password')->assertSessionHasErrors('social')->assertSessionMissing('oauth.flow');
    }

    public function test_provider_sign_in_opens_the_dashboard_instead_of_a_stored_or_security_destination(): void
    {
        $user = User::factory()->create(['password' => null]);
        $this->identity($user);
        $this->remote();
        $this->withSession([...$this->flow(), 'url' => ['intended' => url('/settings/security')]])->get('/auth/google/callback')->assertRedirect('/dashboard')->assertSessionMissing('url.intended')->assertSessionMissing('auth.password_confirmed_at');
        $this->assertAuthenticatedAs($user);
        $this->get('/auth/google/callback?code=replayed')->assertRedirect('/dashboard');
        $this->withSession($this->flow('google', 'link', $user))->get('/auth/google/callback?error=access_denied')->assertRedirect('/settings/security')->assertSessionHasErrors('social');
    }

    public function test_explicit_link_requires_recent_auth_and_cannot_reassign_an_identity(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->identity($other);
        $this->actingAs($user)->post('/settings/social/google')->assertRedirect(route('password.confirm'));
        $this->remote();
        $this->withSession([...$this->flow('google', 'link', $user), 'auth.password_confirmed_at' => time()])->get('/auth/google/callback')->assertSessionHasErrors('social');
        $this->assertSame($other->id, SocialIdentity::query()->firstOrFail()->user_id);
    }

    public function test_link_and_confirm_allow_passwordless_settings_but_wrong_provider_account_does_not(): void
    {
        $user = User::factory()->create(['password' => null]);
        $this->identity($user);
        $this->actingAs($user)->get('/settings/security')->assertRedirect(route('password.confirm'));
        $this->get('/user/confirm-password')->assertInertia(fn (Assert $page) => $page->where('hasPassword', false)->where('connectedProviders', ['google']));
        $this->remote();
        $this->withSession($this->flow('google', 'confirm', $user))->get('/auth/google/callback')->assertRedirect('/settings/security')->assertSessionHas('auth.password_confirmed_at');
        $this->get('/settings/security')->assertInertia(fn (Assert $page) => $page->where('hasPassword', false));
        $this->put('/settings/password', ['password' => 'Strong-test-Password42!', 'password_confirmation' => 'Strong-test-Password42!'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('Strong-test-Password42!', $user->fresh()->password));
    }

    public function test_passwordless_changes_require_recent_auth_and_last_method_cannot_be_removed(): void
    {
        $user = User::factory()->create(['password' => null]);
        $this->identity($user);
        $this->actingAs($user)->put('/settings/password', ['password' => 'Strong-test-Password42!', 'password_confirmation' => 'Strong-test-Password42!'])->assertRedirect(route('password.confirm'));
        $this->delete('/settings/profile', ['confirmation' => 'DELETE'])->assertRedirect(route('password.confirm'));
        $this->withSession(['auth.password_confirmed_at' => time()])->delete('/settings/social/google')->assertSessionHasErrors('social');
        $this->assertDatabaseCount('social_identities', 1);
        $passkey = new Passkey;
        $passkey->forceFill(['user_id' => $user->id, 'name' => 'Test key', 'credential_id' => 'fixture-key', 'credential' => []])->save();
        $this->delete('/settings/social/google')->assertRedirect('/settings/security');
        $this->delete('/user/passkeys/'.$passkey->id)->assertSessionHasErrors('passkey');
        $this->assertDatabaseCount('passkeys', 1);
    }

    public function test_recent_passwordless_delete_needs_explicit_confirmation_and_cascades_identity(): void
    {
        $user = User::factory()->create(['password' => null]);
        $this->identity($user);
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->delete('/settings/profile')->assertSessionHasErrors('confirmation');
        $this->delete('/settings/profile', ['confirmation' => 'DELETE'])->assertRedirect('/');
        $this->assertGuest();
        $this->assertDatabaseCount('social_identities', 0);
    }

    public function test_explicit_link_saves_the_identity_on_the_authenticated_account(): void
    {
        $user = User::factory()->create();
        $this->remote('github', 'github-link', 'other@example.test');
        $this->actingAs($user)->withSession([...$this->flow('github', 'link', $user), 'auth.password_confirmed_at' => time()])->get('/auth/github/callback')->assertRedirect('/settings/security');
        $this->assertDatabaseHas('social_identities', ['user_id' => $user->id, 'provider' => 'github', 'subject' => 'github-link']);
        $this->assertDatabaseCount('users', 1);
        $this->assertAuthenticatedAs($user);
    }

    public function test_provider_confirmation_cannot_use_someone_elses_identity(): void
    {
        $user = User::factory()->create(['password' => null]);
        $this->identity($user);
        $this->remote('google', 'different-subject');
        $this->actingAs($user)->withSession($this->flow('google', 'confirm', $user))->get('/auth/google/callback')->assertRedirect(route('password.confirm'))->assertSessionHasErrors('social')->assertSessionMissing('auth.password_confirmed_at')->assertSessionMissing('admin.two_factor_proof');
        $this->assertAuthenticatedAs($user);
    }
}

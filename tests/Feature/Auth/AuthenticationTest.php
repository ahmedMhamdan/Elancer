<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Validated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get(route('login'));

        $response->assertOk();
    }

    public function test_users_can_authenticate_using_the_login_screen()
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_with_two_factor_enabled_are_redirected_to_two_factor_challenge()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->withTwoFactor()->create();

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $response->assertSessionHas('login.id', $user->id);
        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    #[DataProvider('rememberChoices')]
    public function test_a_successful_password_login_verifies_the_password_only_once(bool $remember): void
    {
        $user = User::factory()->create();
        $hasher = Hash::getFacadeRoot();
        $checks = 0;
        $measured = \Mockery::mock($hasher);
        $measured->shouldReceive('check')->andReturnUsing(function ($plain, $hashed, $options = []) use ($hasher, &$checks) {
            $checks++;

            return $hasher->check($plain, $hashed, $options);
        });
        Hash::swap($measured);

        $response = $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password', 'remember' => $remember])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, $checks);
        if ($remember) {
            $response->assertCookie(auth()->guard('web')->getRecallerName());
        }
    }

    public static function rememberChoices(): array
    {
        return ['session' => [false], 'remembered' => [true]];
    }

    public function test_login_preserves_guard_events_and_password_rehashing(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password', ['rounds' => 4])]);
        config(['hashing.bcrypt.rounds' => 5]);
        Hash::forgetDrivers();
        Event::fake([Attempting::class, Validated::class]);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertFalse(Hash::needsRehash($user->fresh()->password));
        Event::assertDispatched(Attempting::class);
        Event::assertDispatched(Validated::class);
    }

    public function test_unconfirmed_two_factor_setup_keeps_the_normal_login_behavior(): void
    {
        Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]);
        $user = User::factory()->withTwoFactor()->create(['two_factor_confirmed_at' => null]);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertNull(session('login.id'));
    }

    public function test_users_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_users_are_rate_limited()
    {
        $user = User::factory()->create();

        RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertTooManyRequests();
    }
}

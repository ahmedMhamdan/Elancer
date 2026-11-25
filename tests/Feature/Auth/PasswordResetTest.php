<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Features;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::resetPasswords());
    }

    public function test_reset_password_link_screen_can_be_rendered()
    {
        $response = $this->get(route('password.request'));

        $response->assertOk();
    }

    public function test_reset_password_link_can_be_requested()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get(route('password.reset', $notification->token));

            $response->assertOk();

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'Strong-test-Password42!',
                'password_confirmation' => 'Strong-test-Password42!',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    public function test_password_reset_rejects_weak_password_with_valid_token(): void
    {
        $user = User::factory()->create();
        $hash = $user->password;
        $token = Password::createToken($user);
        $this->post(route('password.update'), [
            'token' => $token, 'email' => $user->email,
            'password' => 'weak', 'password_confirmation' => 'weak',
        ])->assertSessionHasErrors('password');
        $this->assertSame($hash, $user->fresh()->password);
    }

    public function test_password_recovery_invalidates_prior_browser_sessions_and_allows_a_fresh_login(): void
    {
        $user = User::factory()->create(['pending_email' => 'pending@example.test', 'email_change_hash' => hash('sha256', 'pending')]);
        $guardKey = Auth::guard('web')->getName();
        $password = 'Strong-test-Password42!';
        $this->post(route('password.update'), [
            'token' => Password::createToken($user), 'email' => $user->email,
            'password' => $password, 'password_confirmation' => $password,
        ])->assertRedirect(route('login'));
        $this->assertNull($user->refresh()->pending_email);
        $this->assertNotNull($user->recovery_generation);
        foreach ([[], ['auth.password_confirmed_at' => time()]] as $previousSession) {
            Auth::forgetGuards();
            $this->withSession([$guardKey => $user->id, ...$previousSession])->get(route('profile.edit'))->assertRedirect(route('login'));
            $this->assertGuest();
        }
        $this->post(route('login.store'), ['email' => $user->email, 'password' => $password])->assertSessionHasNoErrors();
        $this->get(route('profile.edit'))->assertOk();
    }

    public function test_password_cannot_be_reset_with_invalid_token(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'Strong-test-Password42!',
            'password_confirmation' => 'Strong-test-Password42!',
        ]);

        $response->assertSessionHasErrors('email');
    }
}

<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Notifications\VerifyReplacementEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_email_changes_require_recent_authentication_and_proof_from_the_new_mailbox(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $original = $user->email;
        $input = ['name' => 'Test User', 'email' => 'replacement@example.com'];
        $this->actingAs($user)->patch(route('profile.update'), $input)->assertRedirect(route('password.confirm'));
        Notification::assertNothingSent();
        $this->withSession(['auth.password_confirmed_at' => time()])->patch(route('profile.update'), $input)->assertRedirect(route('profile.edit'));
        $this->assertSame($original, $user->refresh()->email);
        $this->assertTrue($user->hasVerifiedEmail());
        Notification::assertSentOnDemand(VerifyReplacementEmail::class, function ($mail, $channels, $recipient) use ($input, $user) {
            $this->assertSame($input['email'], $recipient->routes['mail']);
            $this->actingAs(User::factory()->create())->get($mail->url)->assertForbidden();
            $this->actingAs($user)->get($mail->url)->assertRedirect(route('profile.edit'));
            $this->get($mail->url)->assertForbidden();

            return true;
        });
        $this->assertSame($input['email'], $user->refresh()->email);
        $this->assertTrue($user->hasVerifiedEmail());
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }
}

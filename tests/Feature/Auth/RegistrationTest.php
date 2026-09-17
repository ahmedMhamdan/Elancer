<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Strong-test-Password42!',
            'password_confirmation' => 'Strong-test-Password42!',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registration_rejects_short_passwords_and_each_missing_character_class(): void
    {
        foreach (['password', 'Abcdefghi1!', 'ABCDEFGHIJK1!', 'abcdefghijk1!', 'Abcdefghijk!', 'Abcdefghijk12'] as $index => $password) {
            $email = 'weak-'.$index.'@example.test';
            $this->post(route('register.store'), [
                'name' => 'Password Check', 'email' => $email,
                'password' => $password, 'password_confirmation' => $password,
            ])->assertSessionHasErrors('password');
            $this->assertDatabaseMissing('users', ['email' => $email]);
        }
    }

    public function test_registration_rejects_mismatched_passwords(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Learner',
            'email' => 'learner@example.com',
            'password' => 'Strong-test-Password42!',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'email' => 'learner@example.com',
        ]);
    }
}

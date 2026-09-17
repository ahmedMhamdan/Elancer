<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_json_language_save_returns_only_locale_without_redirecting_or_changing_another_account(): void
    {
        $user = User::factory()->create(['locale' => 'en']);
        $other = User::factory()->create(['locale' => 'en']);
        $this->actingAs($user)->postJson('/locale', ['locale' => 'ar', 'user_id' => $other->id])
            ->assertOk()->assertExactJson(['locale' => 'ar'])->assertCookie('locale', 'ar')
            ->assertSessionHas('locale', 'ar')->assertHeaderMissing('Location');
        $this->assertSame('ar', $user->fresh()->locale);
        $this->assertSame('en', $other->fresh()->locale);
        $this->postJson('/locale', ['locale' => 'invalid'])->assertUnprocessable();
        $this->assertSame('ar', $user->fresh()->locale);
    }

    public function test_guest_can_switch_language_and_reload_with_rtl(): void
    {
        $this->from('/')->post('/locale', ['locale' => 'ar'])
            ->assertRedirect('/')->assertSessionHas('locale', 'ar')->assertCookie('locale', 'ar');
        $this->get('/')->assertSee('dir="rtl"', false)
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'ar'));
        $this->from('/')->post('/locale', ['locale' => 'en'])->assertRedirect('/');
        $this->get('/')->assertSee('dir="ltr"', false);
    }

    public function test_guest_cookie_is_used_and_invalid_cookie_falls_back_to_english(): void
    {
        $this->withCookie('locale', 'ar')->get('/')
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'ar'));
        $this->withCookie('locale', 'invalid')->get('/')
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
    }

    public function test_member_preference_wins_and_only_the_current_member_is_updated(): void
    {
        $user = User::factory()->create(['locale' => 'ar']);
        $other = User::factory()->create(['locale' => 'ar']);
        $this->actingAs($user)->withSession(['locale' => 'en'])->get('/')
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'ar'));
        $this->post('/locale', ['locale' => 'en', 'user_id' => $other->id, 'is_admin' => true])
            ->assertSessionHasNoErrors();
        $this->assertSame('en', $user->fresh()->locale);
        $this->assertFalse($user->fresh()->is_admin);
        $this->assertSame('ar', $other->fresh()->locale);
    }

    public function test_unsupported_language_does_not_change_saved_preferences(): void
    {
        $user = User::factory()->create(['locale' => 'ar']);
        $this->actingAs($user)->withSession(['locale' => 'ar'])
            ->post('/locale', ['locale' => 'fr'])->assertSessionHasErrors('locale')
            ->assertSessionHas('locale', 'ar');
        $this->assertSame('ar', $user->fresh()->locale);
        $this->post('/locale', ['locale' => ['ar']])->assertSessionHasErrors('locale');
    }

    public function test_validation_and_authentication_errors_follow_selected_language(): void
    {
        $this->withSession(['locale' => 'ar'])->post('/register', [])->assertSessionHasErrors([
            'email' => 'حقل البريد الإلكتروني مطلوب.',
        ]);
        $this->post('/login', ['email' => 'missing@example.test', 'password' => 'invalid'])
            ->assertSessionHasErrors(['email' => 'بيانات الدخول غير صحيحة.']);
    }

    public function test_notifications_use_the_members_saved_language(): void
    {
        $user = User::factory()->make(['locale' => 'ar']);
        $this->assertSame('ar', $user->preferredLocale());
        app()->setLocale($user->preferredLocale());
        $message = (new ResetPassword('test-token'))->toMail($user);
        $this->assertSame('إعادة تعيين كلمة المرور', $message->subject);
    }

    public function test_registration_keeps_the_selected_language(): void
    {
        $this->withSession(['locale' => 'ar'])->post('/register', [
            'name' => 'Locale Test',
            'email' => 'locale@example.test',
            'password' => 'Strong-test-Password42!',
            'password_confirmation' => 'Strong-test-Password42!',
        ])->assertSessionHasNoErrors();
        $this->assertSame('ar', User::where('email', 'locale@example.test')->firstOrFail()->locale);
    }

    public function test_arabic_profile_and_identity_validation_do_not_fall_back_to_english(): void
    {
        $user = User::factory()->create(['locale' => 'ar', 'onboarding_completed_at' => now()]);
        $this->actingAs($user)->patch('/my-profile', ['skills' => ['missing-skill']])
            ->assertSessionHasErrors(['skills.0' => 'قيمة المهارة غير موجودة ضمن الخيارات المتاحة.']);
        $this->post('/my-profile/identity', [])->assertSessionHasErrors([
            'government_id' => 'حقل صورة وثيقة الهوية مطلوب.',
            'selfie' => 'حقل الصورة الشخصية للوجه مطلوب.',
            'consent' => 'يجب الموافقة على إرسال الصور لمراجعة الهوية.',
        ]);
    }

    public function test_arabic_security_validation_and_operational_messages_are_translated(): void
    {
        $user = User::factory()->create(['locale' => 'ar', 'onboarding_completed_at' => now()]);
        $this->actingAs($user)->put('/settings/password', [
            'current_password' => 'wrong-password',
            'password' => 'Strong-test-Password42!',
            'password_confirmation' => 'Strong-test-Password42!',
        ])->assertSessionHasErrors(['current_password' => 'كلمة المرور الحالية غير صحيحة.']);
        $this->assertSame('تم تحديث بيانات الحساب.', __('Profile updated.'));
        $this->assertSame('تم تغيير كلمة المرور.', __('Password updated.'));
        $this->assertSame('رمز المصادقة الثنائية غير صحيح.', __('The provided two factor authentication code was invalid.'));
    }
}

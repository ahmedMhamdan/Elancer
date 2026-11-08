<?php

namespace App\Actions\Auth;

use App\Actions\Demo\DemoWorld;
use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Plan 025: the prepared accounts a visitor may enter without registering, and whether a request
 * belongs to a session opened that way. The limits follow that session, never the account itself.
 */
class DemoAccess
{
    public const ROLES = ['client', 'freelancer', 'admin'];

    public const SESSION_KEY = 'demo';

    public const ADMIN_KEY = 'demo_admin';

    /**
     * The roles a visitor can enter now.
     *
     * @return list<string>
     */
    public static function roles(): array
    {
        return array_values(array_filter(self::ROLES, fn (string $role): bool => self::account($role) !== null));
    }

    /** Never an account that could do more than look around, and never past a second factor. */
    public static function account(string $role): ?User
    {
        // The admin preview borrows an ordinary prepared account; it never grants database privileges.
        if ($role === 'admin') {
            foreach (['client', 'freelancer'] as $workspace) {
                $user = self::account($workspace);
                if ($user !== null && DemoWorld::address($user->email)) {
                    return $user;
                }
            }

            return null;
        }
        $email = config('demo.accounts.'.$role);
        if (! config('demo.enabled') || ! in_array($role, self::ROLES, true) || ! is_string($email) || trim($email) === '') {
            return null;
        }
        $user = User::query()->where('email', trim($email))->first();

        return $user !== null && $user->status === AccountStatus::Active && $user->hasVerifiedEmail() && $user->onboarding_completed_at !== null
            && ! $user->isAdministrator() && $user->two_factor_secret === null ? $user : null;
    }

    public static function active(Request $request): bool
    {
        return $request->hasSession() && $request->session()->get(self::SESSION_KEY) === true && $request->user() !== null;
    }

    /** End only the shared visit, keeping the visitor's language for their own sign-in. */
    public static function leave(Request $request): void
    {
        $locale = app()->getLocale();
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put('locale', $locale);
    }

    public static function administrator(Request $request): bool
    {
        $user = $request->user();

        return $user !== null && self::active($request) && $request->session()->get(self::ADMIN_KEY) === true
            && ! $user->isAdministrator() && DemoWorld::address($user->email);
    }

    /** Plan 027: the other prepared account, which the banner offers so one visitor can play both sides of a hire. */
    public static function other(?User $user): ?string
    {
        foreach (['client', 'freelancer'] as $role) {
            if ($user !== null && strcasecmp(trim((string) config('demo.accounts.'.$role)), $user->email) !== 0 && self::account($role) !== null) {
                return $role;
            }
        }

        return null;
    }
}

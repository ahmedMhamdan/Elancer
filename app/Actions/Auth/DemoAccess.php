<?php

namespace App\Actions\Auth;

use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Plan 025: the prepared accounts a visitor may enter without registering, and whether a request
 * belongs to a session opened that way. The limits follow that session, never the account itself.
 */
class DemoAccess
{
    public const ROLES = ['client', 'freelancer'];

    public const SESSION_KEY = 'demo';

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

    /** Plan 027: the other prepared account, which the banner offers so one visitor can play both sides of a hire. */
    public static function other(?User $user): ?string
    {
        foreach (self::ROLES as $role) {
            if ($user !== null && strcasecmp(trim((string) config('demo.accounts.'.$role)), $user->email) !== 0 && self::account($role) !== null) {
                return $role;
            }
        }

        return null;
    }
}

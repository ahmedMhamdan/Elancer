<?php

namespace App\Actions\Auth;

use App\Models\SocialIdentity;
use App\Models\User;
use Laravel\Fortify\Features;

class SocialProviders
{
    public const NAMES = ['google', 'github'];

    public static function enabled(string $provider): bool
    {
        if (! in_array($provider, self::NAMES, true)) {
            return false;
        }
        foreach (['client_id', 'client_secret', 'redirect'] as $key) {
            if (! is_string(config("services.$provider.$key")) || trim(config("services.$provider.$key")) === '') {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, bool> */
    public static function availability(): array
    {
        return ['google' => self::enabled('google'), 'github' => self::enabled('github')];
    }

    /** @return list<string> */
    public static function connected(User $user): array
    {
        return array_values(SocialIdentity::query()->where('user_id', $user->id)->get()->map(fn (SocialIdentity $identity): string => $identity->provider)->all());
    }

    public static function hasOtherMethod(User $user, ?string $excludedProvider = null, ?int $excludedPasskey = null): bool
    {
        if ($user->password !== null && $user->password !== '') {
            return true;
        }
        if (Features::canManagePasskeys() && $user->passkeys()->when($excludedPasskey !== null, fn ($q) => $q->whereKeyNot($excludedPasskey))->exists()) {
            return true;
        }
        foreach (self::connected($user) as $provider) {
            if ($provider !== $excludedProvider && self::enabled($provider)) {
                return true;
            }
        }

        return false;
    }
}

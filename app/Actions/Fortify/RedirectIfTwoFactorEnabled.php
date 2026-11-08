<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Auth\SessionGuard;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

class RedirectIfTwoFactorEnabled extends RedirectIfTwoFactorAuthenticatable
{
    public function handle($request, $next)
    {
        $guard = $this->guard;
        if ($guard instanceof SessionGuard && Fortify::$authenticateUsingCallback === null) {
            $user = $guard->getProvider()->retrieveByCredentials($request->only(Fortify::username(), 'password'));
            // The next Fortify stage verifies this password; no second-factor check is needed here.
            if ($user instanceof User && $user->two_factor_secret === null) {
                return $next($request);
            }
        }

        return parent::handle($request, $next);
    }
}

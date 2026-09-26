<?php

namespace App\Http\Middleware;

use App\Actions\Auth\SocialProviders;
use App\Models\User;
use Closure;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ProtectPasswordlessAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $user->password !== null) {
            return $next($request);
        }
        if (! $request->routeIs('profile.update', 'profile.destroy', 'user-password.update', 'passkey.destroy')) {
            return $next($request);
        }

        return app(RequirePassword::class)->handle($request, function (Request $request) use ($next, $user): Response {
            if (! $request->routeIs('passkey.destroy')) {
                return $next($request);
            }

            return DB::transaction(function () use ($request, $next, $user): Response {
                $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                $passkey = $request->route('passkey');
                $id = $passkey instanceof Model ? (int) $passkey->getKey() : (int) $passkey;
                if (! SocialProviders::hasOtherMethod($locked, null, $id)) {
                    throw ValidationException::withMessages(['passkey' => __('Keep at least one usable sign-in method. Add a password, passkey or another provider first.')]);
                }

                return $next($request);
            }, 3);
        });
    }
}

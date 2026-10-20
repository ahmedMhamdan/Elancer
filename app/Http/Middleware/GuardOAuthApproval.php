<?php

namespace App\Http\Middleware;

use App\Mcp\AgentAccess;
use App\Models\User;
use Closure;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\Request;
use Laravel\Passport\Bridge\Client;
use Laravel\Passport\Bridge\Scope;
use Laravel\Passport\Bridge\User as BridgeUser;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * Plan 033: connecting an app is creating a credential, so the approval page asks for a recent sign-in
 * as the tokens page does (plan 028), and approving checks the account again on the server: never a demo
 * account, an account that may not act, or one already holding the most connected apps.
 */
class GuardOAuthApproval
{
    public function __construct(private RequirePassword $password) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        // Passport sends a guest to the login page, which returns here.
        if (! $user instanceof User) {
            return $next($request);
        }
        if ($request->isMethod('post')) {
            $refusal = AgentAccess::refusal($user, self::client($request));
            abort_if($refusal !== null, 403, (string) $refusal);

            return $next($request);
        }

        return $this->password->handle($request, $next);
    }

    /** The app being approved, as Passport kept it in the session when it showed the page. */
    private static function client(Request $request): ?string
    {
        $kept = $request->session()->get('authRequest');
        $authRequest = is_string($kept) ? unserialize($kept, ['allowed_classes' => [AuthorizationRequest::class, Client::class, Scope::class, BridgeUser::class]]) : null;

        return $authRequest instanceof AuthorizationRequest ? $authRequest->getClient()->getIdentifier() : null;
    }
}

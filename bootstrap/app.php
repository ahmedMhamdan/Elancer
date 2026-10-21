<?php

use App\Http\Middleware\EnsureApiAccount;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ProtectPasswordlessAccount;
use App\Http\Middleware\RestrictDemoSession;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrustHostProxy;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\TokenMismatchException;
use Laravel\Passport\Exceptions\InvalidAuthTokenException;
use Laravel\Passport\Exceptions\OAuthServerException as PassportOAuthServerException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use League\OAuth2\Server\Exception\OAuthServerException as LeagueOAuthServerException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // Plan 028: /api/v1, signed in by bearer token only.
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);
        // Provider callbacks carry a signature instead of a session token.
        $middleware->validateCsrfTokens(except: ['payments/webhooks/*']);
        // The host ends HTTPS in front of the container, so its forwarded scheme is trusted. The forwarded
        // client address and host are not: a visitor could set them and slip past per-address limits.
        // TrustHostProxy takes the visitor's address from the host's own header where one is configured.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_PROTO);
        $middleware->replace(TrustProxies::class, TrustHostProxy::class);

        // Plan 028: what a token may do is checked beside each API route.
        $middleware->alias(['abilities' => CheckAbilities::class, 'api.account' => EnsureApiAccount::class]);
        // Both answer before a record is looked up, so a token that may not ask learns nothing about which numbers exist.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: EnsureApiAccount::class);
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: CheckAbilities::class);

        $middleware->web(append: [
            HandleAppearance::class,
            SetLocale::class,
            ProtectPasswordlessAccount::class,
            HandleInertiaRequests::class,
            // Inside the Inertia middleware, so a refusal is answered the way an Inertia visit expects.
            RestrictDemoSession::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Plan 033: an agent and an app signing in read JSON, never a redirect to the login page.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'mcp', 'oauth/token', 'oauth/register') || $request->expectsJson(),
        );
        // An expired or foreign OAuth token is the caller's error, answered with 401; it is not logged.
        $exceptions->dontReport([LeagueOAuthServerException::class, PassportOAuthServerException::class]);
        // Passport keeps one approval waiting per session. A form from an older page (the page opened again
        // in another tab, or a session that has ended) gets Elancer's page saying to start again from the app,
        // instead of Passport's bare 403, a 419, or the login page, after which no approval is waiting.
        // Laravel has wrapped the first two by now.
        $approving = fn (Request $request): bool => $request->routeIs('passport.authorizations.approve', 'passport.authorizations.deny');
        $exceptions->render(fn (HttpException $exception, Request $request) => $approving($request)
            && ($exception->getPrevious() instanceof InvalidAuthTokenException || $exception->getPrevious() instanceof TokenMismatchException)
            ? redirect()->route('oauth.expired', status: 303) : null);
        $exceptions->render(fn (AuthenticationException $exception, Request $request) => $approving($request) ? redirect()->route('oauth.expired', status: 303) : null);
        // A signed-out image request must not become the page opened after the next login.
        $exceptions->render(fn (AuthenticationException $exception, Request $request) => $request->routeIs('profile.photo', 'admin.identity.image') ? response('', 401) : null);
    })->create();

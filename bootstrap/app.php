<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ProtectPasswordlessAccount;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
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
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_PROTO);

        $middleware->web(append: [
            HandleAppearance::class,
            SetLocale::class,
            ProtectPasswordlessAccount::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // A signed-out image request must not become the page opened after the next login.
        $exceptions->render(fn (AuthenticationException $exception, Request $request) => $request->routeIs('profile.photo', 'admin.identity.image') ? response('', 401) : null);
    })->create();

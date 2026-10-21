<?php

use App\Http\Middleware\AuthenticateAgent;
use App\Http\Middleware\GuardOAuthApproval;
use App\Mcp\Servers\ElancerServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController;
use Laravel\Passport\Http\Controllers\AuthorizationController;
use Laravel\Passport\Http\Controllers\DenyAuthorizationController;

// Plan 033: Elancer for members' own AI agents. laravel/mcp loads this file outside the web group, so
// nothing here has a session unless it asks for one.

// Discovery documents and dynamic client registration, so an app finds the sign-in on its own.
Route::middleware('throttle:oauth-register')->group(fn () => Mcp::oauthRoutes());

// Only the part of Passport the Connect flow uses, under Passport's own route names (its route file is
// not loaded): no device codes, no JSON API, no cookie tokens.
Route::prefix('oauth')->name('passport.')->group(function (): void {
    // The app exchanges its code (with the PKCE verifier) or a refresh token here, without a session.
    Route::post('token', [AccessTokenController::class, 'issueToken'])->middleware('throttle:oauth-token')->name('token');
    Route::middleware('web')->group(function (): void {
        // A guest is sent to the login page by Passport and comes back here.
        Route::get('authorize', [AuthorizationController::class, 'authorize'])->middleware(GuardOAuthApproval::class)->name('authorizations.authorize');
        Route::middleware('auth')->group(function (): void {
            Route::post('authorize', [ApproveAuthorizationController::class, 'approve'])->middleware(GuardOAuthApproval::class)->name('authorizations.approve');
            Route::delete('authorize', [DenyAuthorizationController::class, 'deny'])->name('authorizations.deny');
        });
    });
});

// Where an approval form that no longer matches the session lands (bootstrap/app.php): it tells the
// member to start again from the app, instead of Passport's bare 403. Open to guests: the session may be gone.
Route::middleware('web')->group(fn () => Route::inertia('oauth/expired', 'oauth/expired')->name('oauth.expired'));

// Either kind of bearer token, the account rule of the API, then 60 calls a minute per token.
Mcp::web('/mcp', ElancerServer::class)->middleware([AuthenticateAgent::class, 'api.account', 'throttle:mcp'])->name('mcp');

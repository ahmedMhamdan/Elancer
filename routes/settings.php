<?php

use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Settings\ApiTokenController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
    Route::get('settings/notifications', [NotificationController::class, 'preferences'])->name('notification-preferences.edit');
    Route::patch('settings/notifications', [NotificationController::class, 'updatePreferences'])->name('notification-preferences.update');

    // Plan 028: API tokens are managed behind a recent sign-in, like the security page.
    Route::get('settings/tokens', [ApiTokenController::class, 'index'])->middleware(RequirePassword::class)->name('api-tokens.index');
    Route::post('settings/tokens', [ApiTokenController::class, 'store'])->middleware([RequirePassword::class, 'throttle:10,1'])->name('api-tokens.store');
    Route::delete('settings/tokens/{token}', [ApiTokenController::class, 'destroy'])->whereNumber('token')->middleware([RequirePassword::class, 'throttle:30,1'])->name('api-tokens.destroy');
    // Plan 033: how to connect an AI agent and the apps connected through Connect, behind the same sign-in
    // (its own tab since plan 032 part 9).
    Route::get('settings/agents', [ApiTokenController::class, 'agents'])->middleware(RequirePassword::class)->name('ai-agents.index');
    Route::delete('settings/connections/{client}', [ApiTokenController::class, 'disconnect'])->whereUuid('client')->middleware([RequirePassword::class, 'throttle:30,1'])->name('api-tokens.disconnect');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');

<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MarketplaceProfileController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProfilePhotoController;
use App\Http\Middleware\EnsureOnboardingIsComplete;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('onboarding', OnboardingController::class)->name('onboarding');
    Route::post('onboarding', [OnboardingController::class, 'store'])->middleware('throttle:10,1')->name('onboarding.store');
    Route::get('account/profile-photo', ProfilePhotoController::class)->name('profile.photo');
    Route::middleware(EnsureOnboardingIsComplete::class)->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::patch('dashboard/profile', MarketplaceProfileController::class)->name('dashboard.profile.update');
    });
});

require __DIR__.'/settings.php';

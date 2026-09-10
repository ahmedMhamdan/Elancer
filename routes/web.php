<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MarketplaceProfileController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::patch('dashboard/profile', MarketplaceProfileController::class)->name('dashboard.profile.update');
});

require __DIR__.'/settings.php';

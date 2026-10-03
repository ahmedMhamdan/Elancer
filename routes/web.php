<?php

use App\Http\Controllers\AdministratorController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ClientProjectController;
use App\Http\Controllers\ContactBlockController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\FreelancerController;
use App\Http\Controllers\IdentityVerificationController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MarketplaceProfileController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfilePhotoController;
use App\Http\Controllers\ProjectDiscoveryController;
use App\Http\Controllers\ProposalController;
use App\Http\Controllers\SkillController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\UpdateProfilePhotoController;
use App\Http\Middleware\EnsureCategoryAdministrator;
use App\Http\Middleware\EnsureOnboardingIsComplete;
use App\Http\Middleware\EnsureSuperAdministrator;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

foreach (['how-it-works', 'project-guides', 'inspiration', 'why-elancer', 'resources'] as $guide) {
    Route::inertia('learn/'.$guide, 'learn/show', ['guide' => $guide])->name('learn.'.$guide);
}

Route::get('search/filters', [ProjectDiscoveryController::class, 'filters'])->middleware('throttle:60,1')->name('search.filters');
Route::get('categories', [ProjectDiscoveryController::class, 'categories'])->name('categories.index');
Route::get('jobs', [ProjectDiscoveryController::class, 'index'])->name('jobs.index');
Route::get('jobs/{project}', [ProjectDiscoveryController::class, 'show'])->whereNumber('project')->name('jobs.show');
Route::get('freelancers', [FreelancerController::class, 'index'])->name('freelancers.index');
Route::get('freelancers/{profile}', [FreelancerController::class, 'show'])->whereNumber('profile')->name('freelancers.show');
Route::get('freelancers/{profile}/photo', [FreelancerController::class, 'photo'])->whereNumber('profile')->name('freelancers.photo');
Route::post('locale', LocaleController::class)->middleware('throttle:60,1')->name('locale.update');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('skills', SkillController::class)->middleware('throttle:120,1')->name('skills.index');
    Route::get('onboarding', OnboardingController::class)->name('onboarding');
    Route::post('onboarding', [OnboardingController::class, 'store'])->middleware(['throttle:10,1', 'throttle:profile-photos'])->name('onboarding.store');
    Route::get('account/profile-photo', ProfilePhotoController::class)->name('profile.photo');
    Route::middleware(EnsureOnboardingIsComplete::class)->group(function () {
        Route::post('invitations/{invitation}/block', [ContactBlockController::class, 'fromInvitation'])->middleware('throttle:20,1')->name('invitations.block');
        Route::get('offers', [OfferController::class, 'index'])->name('offers.index');
        Route::get('proposals/{proposal}/offer', [OfferController::class, 'create'])->name('offers.create');
        Route::post('proposals/{proposal}/offer', [OfferController::class, 'store'])->middleware('throttle:20,1')->name('offers.store');
        Route::get('offers/{offer}', [OfferController::class, 'show'])->whereNumber('offer')->name('offers.show');
        Route::patch('offers/{offer}', [OfferController::class, 'update'])->whereNumber('offer')->middleware('throttle:30,1')->name('offers.update');
        Route::get('contracts', [ContractController::class, 'index'])->name('contracts.index');
        Route::get('contracts/{contract}', [ContractController::class, 'show'])->whereNumber('contract')->name('contracts.show');
        Route::get('finance', FinanceController::class)->name('finance.index');
        Route::post('contracts/{contract}/payments', [PaymentController::class, 'store'])->whereNumber('contract')->middleware('throttle:20,1')->name('payments.store');
        Route::get('payments/{attempt}/simulator', [PaymentController::class, 'simulator'])->whereNumber('attempt')->name('payments.simulator');
        Route::post('payments/{attempt}/simulator', [PaymentController::class, 'decide'])->whereNumber('attempt')->middleware('throttle:20,1')->name('payments.decide');
        Route::get('payments/{attempt}/return', [PaymentController::class, 'returned'])->whereNumber('attempt')->middleware('throttle:30,1')->name('payments.return');
        Route::post('payments/{attempt}/check', [PaymentController::class, 'check'])->whereNumber('attempt')->middleware('throttle:30,1')->name('payments.check');
        Route::post('payments/{attempt}/cancel', [PaymentController::class, 'cancel'])->whereNumber('attempt')->middleware('throttle:20,1')->name('payments.cancel');
        Route::get('notifications', [NotificationController::class, 'index'])->middleware('throttle:120,1')->name('notifications.index');
        Route::post('notifications/read', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::patch('notifications/{notification}', [NotificationController::class, 'open'])->whereUuid('notification')->name('notifications.open');
        Route::get('messages', [ConversationController::class, 'index'])->name('messages.index');
        Route::post('proposals/{proposal}/conversation', [ConversationController::class, 'start'])->middleware('throttle:20,1')->name('messages.start');
        Route::get('messages/{conversation}', [ConversationController::class, 'show'])->whereNumber('conversation')->name('messages.show');
        Route::post('messages/{conversation}', [ConversationController::class, 'send'])->whereNumber('conversation')->middleware('throttle:60,1')->name('messages.send');
        Route::patch('messages/{conversation}/state', [ConversationController::class, 'state'])->whereNumber('conversation')->name('messages.state');
        Route::patch('messages/items/{message}', [ConversationController::class, 'edit'])->whereNumber('message')->middleware('throttle:30,1')->name('messages.edit');
        Route::post('messages/{conversation}/block', [ContactBlockController::class, 'fromConversation'])->whereNumber('conversation')->middleware('throttle:20,1')->name('messages.block');
        Route::get('invitations', [InvitationController::class, 'index'])->name('invitations.index');
        Route::get('freelancers/{profile}/invite', [InvitationController::class, 'create'])->name('invitations.create');
        Route::post('freelancers/{profile}/invite', [InvitationController::class, 'store'])->middleware('throttle:20,1')->name('invitations.store');
        Route::get('invitations/{invitation}', [InvitationController::class, 'show'])->name('invitations.show');
        Route::patch('invitations/{invitation}', [InvitationController::class, 'update'])->middleware('throttle:30,1')->name('invitations.update');
        Route::get('blocked-accounts', [ContactBlockController::class, 'index'])->name('contacts.blocked');
        Route::post('freelancers/{profile}/block', [ContactBlockController::class, 'store'])->middleware('throttle:20,1')->name('contacts.block');
        Route::delete('blocked-accounts/{block}', [ContactBlockController::class, 'destroy'])->whereNumber('block')->name('contacts.unblock');
        Route::patch('my-profile/publication', [FreelancerController::class, 'publication'])->name('freelancers.publication');
        Route::get('my-proposals', [ProposalController::class, 'index'])->name('proposals.index');
        Route::get('jobs/{project}/apply', [ProposalController::class, 'edit'])->name('proposals.edit');
        Route::put('jobs/{project}/proposal', [ProposalController::class, 'save'])->middleware('throttle:120,1')->name('proposals.save');
        Route::get('proposals/{proposal}', [ProposalController::class, 'show'])->name('proposals.show');
        Route::post('proposals/{proposal}/withdraw', [ProposalController::class, 'withdraw'])->name('proposals.withdraw');
        Route::patch('proposals/{proposal}/review', [ProposalController::class, 'review'])->name('proposals.review');
        Route::get('my-projects/{project}/proposals', [ProposalController::class, 'applicants'])->name('proposals.applicants');
        Route::get('my-projects/{project}/proposals/compare', [ProposalController::class, 'compare'])->name('proposals.compare');
        Route::get('my-profile', DashboardController::class)->name('marketplace-profile.edit');
        Route::post('my-profile/photo', UpdateProfilePhotoController::class)->middleware('throttle:profile-photos')->name('profile.photo.update');
        Route::patch('my-profile', MarketplaceProfileController::class)->name('marketplace-profile.update');
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::patch('dashboard/profile', MarketplaceProfileController::class)->name('dashboard.profile.update');
    });
});

Route::middleware('guest')->group(function () {
    Route::get('auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])->whereIn('provider', ['google', 'github'])->middleware('throttle:10,1')->name('social.redirect');
    Route::get('auth/complete', [SocialAuthController::class, 'complete'])->name('social.complete');
    Route::post('auth/complete', [SocialAuthController::class, 'store'])->middleware('throttle:6,1')->name('social.store');
});
Route::get('auth/{provider}/callback', [SocialAuthController::class, 'callback'])->whereIn('provider', ['google', 'github'])->middleware('throttle:20,1')->name('social.callback');
Route::middleware('auth')->group(function () {
    Route::post('settings/social/{provider}/confirm', [SocialAuthController::class, 'confirm'])->whereIn('provider', ['google', 'github'])->middleware('throttle:10,1')->name('social.confirm');
    Route::post('settings/social/{provider}', [SocialAuthController::class, 'connect'])->whereIn('provider', ['google', 'github'])->middleware(['verified', 'password.confirm', 'throttle:10,1'])->name('social.connect');
    Route::delete('settings/social/{provider}', [SocialAuthController::class, 'disconnect'])->whereIn('provider', ['google', 'github'])->middleware(['verified', 'password.confirm', 'throttle:10,1'])->name('social.disconnect');
});
require __DIR__.'/settings.php';

Route::middleware(['auth', 'verified', EnsureCategoryAdministrator::class])
    ->prefix('admin/categories')->name('admin.categories.')->group(function () {
        Route::get('/', [CategoryController::class, 'index'])->name('index');
        Route::get('create', [CategoryController::class, 'create'])->name('create');
        Route::post('/', [CategoryController::class, 'store'])->name('store');
        Route::get('{category}/edit', [CategoryController::class, 'edit'])->name('edit');
        Route::put('{category}', [CategoryController::class, 'update'])->name('update');
        Route::delete('{category}', [CategoryController::class, 'destroy'])->name('destroy');
        Route::delete('{category}/permanent', [CategoryController::class, 'forceDestroy'])->withTrashed()->name('force-destroy');
        Route::post('{category}/restore', [CategoryController::class, 'restore'])->withTrashed()->name('restore');
    });

Route::middleware(['auth', 'verified', EnsureSuperAdministrator::class])
    ->prefix('admin/administrators')->name('admin.administrators.')->group(function () {
        Route::get('/', [AdministratorController::class, 'index'])->name('index');
        Route::get('{user}/edit', [AdministratorController::class, 'edit'])->name('edit');
        Route::put('{user}', [AdministratorController::class, 'update'])->middleware('throttle:20,1')->name('update');
    });

Route::post('my-profile/identity', [IdentityVerificationController::class, 'store'])
    ->middleware(['auth', 'verified', EnsureOnboardingIsComplete::class, 'throttle:5,1'])->name('identity.store');
Route::middleware(['auth', 'verified', EnsureSuperAdministrator::class])->prefix('admin/identity')->name('admin.identity.')->group(function () {
    Route::get('/', [IdentityVerificationController::class, 'index'])->name('index');
    Route::get('{verification}/image/{kind}', [IdentityVerificationController::class, 'image'])->name('image');
    Route::put('{verification}', [IdentityVerificationController::class, 'review'])->middleware('throttle:20,1')->name('review');
});

Route::middleware(['auth', 'verified', EnsureOnboardingIsComplete::class])->prefix('my-projects')->name('projects.')->group(function () {
    $controller = ClientProjectController::class;
    Route::get('/', [$controller, 'index'])->name('index');
    Route::post('/', [$controller, 'store'])->middleware('throttle:20,1')->name('store');
    Route::get('{project}/edit', [$controller, 'edit'])->name('edit');
    Route::patch('{project}', [$controller, 'update'])->middleware('throttle:120,1')->name('update');
    Route::post('{project}/publish', [$controller, 'publish'])->middleware('throttle:10,1')->name('publish');
    Route::delete('{project}', [$controller, 'destroy'])->name('destroy');
    Route::post('{project}/restore', [$controller, 'restore'])->withTrashed()->name('restore');
    Route::delete('{project}/permanent', [$controller, 'forceDestroy'])->withTrashed()->name('force-destroy');
});

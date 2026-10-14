<?php

use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ProposalController;
use Illuminate\Support\Facades\Route;

// Plan 028: a focused API for programs a member runs themselves. Every request carries a bearer token made on
// settings/tokens; there is no session here. Each route names the one ability it needs, and the account check
// and the proposal rules are the ones the pages use.
Route::prefix('v1')->name('api.v1.')->middleware(['auth:sanctum', 'api.account'])->group(function () {
    Route::middleware('throttle:api-read')->group(function () {
        Route::get('me', MeController::class)->name('me');
        Route::get('projects', [ProjectController::class, 'index'])->middleware('abilities:projects:read')->name('projects.index');
        Route::get('projects/{project}', [ProjectController::class, 'show'])->whereNumber('project')->middleware('abilities:projects:read')->name('projects.show');
        Route::get('me/proposals', [ProposalController::class, 'index'])->middleware('abilities:proposals:read')->name('proposals.index');
        Route::get('me/proposals/{proposal}', [ProposalController::class, 'show'])->whereNumber('proposal')->middleware('abilities:proposals:read')->name('proposals.show');
    });
    Route::middleware(['throttle:api-write', 'abilities:proposals:write'])->group(function () {
        Route::put('projects/{project}/proposal', [ProposalController::class, 'save'])->whereNumber('project')->name('proposals.save');
        Route::post('me/proposals/{proposal}/withdraw', [ProposalController::class, 'withdraw'])->whereNumber('proposal')->name('proposals.withdraw');
    });
});

<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profiles\SaveProfileRequest;
use Illuminate\Http\RedirectResponse;

class MarketplaceProfileController extends Controller
{
    public function __invoke(SaveProfileRequest $request): RedirectResponse
    {
        $request->user()->profile()->updateOrCreate([], $request->validated());

        return to_route('dashboard');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\IdentityVerification;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->profile;

        return Inertia::render($request->routeIs('marketplace-profile.edit') ? 'marketplace-profile' : 'dashboard', [
            'identity' => IdentityVerification::where('user_id', $user->id)->first()?->only(['status', 'reason', 'reviewed_at']),
            'profile' => $profile?->only([
                'headline',
                'bio',
                'location',
                'published_at',
                'country',
                'city',
                'company',
                'skills',
            ]),
        ]);
    }
}

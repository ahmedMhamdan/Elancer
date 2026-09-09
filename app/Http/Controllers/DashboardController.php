<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $profile = $user->profile;

        return Inertia::render('dashboard', [
            'profile' => $profile?->only([
                'headline',
                'bio',
                'location',
                'published_at',
            ]),
        ]);
    }
}

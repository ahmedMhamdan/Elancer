<?php

namespace App\Http\Controllers;

use App\Actions\CompleteOnboarding;
use App\Http\Requests\CompleteOnboardingRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->canParticipateInMarketplace(), 403);

        if ($user->onboarding_completed_at !== null) {
            return to_route('dashboard');
        }

        return Inertia::render('onboarding', [
            'initial' => [
                'role' => $user->workspace_role->value ?? '',
                'name' => $user->name,
                'headline' => $user->profile->headline ?? '',
                'bio' => $user->profile->bio ?? '',
                'skills' => $user->profile->skills ?? [],
                'company' => $user->profile->company ?? '',
                'country' => $user->profile->country ?? '',
                'city' => $user->profile->city ?? '',
            ],
            'submitUrl' => route('onboarding.store'),
        ]);
    }

    public function store(CompleteOnboardingRequest $request, CompleteOnboarding $complete): RedirectResponse
    {
        /** @var array{role: string, name: string, bio: string, country: string, city: string, headline?: string, company?: string|null, skills?: list<string>} $data */
        $data = $request->safe()->except('photo');
        if ($complete->handle($request->user(), $data, $request->file('photo'))) {
            $request->session()->flash('onboarding_ready', true);
        }

        return to_route('dashboard');
    }
}

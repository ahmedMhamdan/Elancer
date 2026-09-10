<?php

namespace App\Http\Responses;

use Laravel\Fortify\Http\Responses\VerifyEmailResponse;

class OnboardingVerifyEmailResponse extends VerifyEmailResponse
{
    public function toResponse($request)
    {
        if (! $request->wantsJson() && $request->user()?->onboarding_completed_at === null) {
            return redirect()->route('onboarding');
        }

        return parent::toResponse($request);
    }
}

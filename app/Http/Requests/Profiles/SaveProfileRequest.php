<?php

namespace App\Http\Requests\Profiles;

class SaveProfileRequest extends UpdateProfileRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->profile
            ? $user->can('update', $user->profile)
            : $user->canParticipateInMarketplace());
    }
}

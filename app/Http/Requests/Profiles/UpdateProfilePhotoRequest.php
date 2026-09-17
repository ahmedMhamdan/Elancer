<?php

namespace App\Http\Requests\Profiles;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfilePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canParticipateInMarketplace() === true
            && $this->user()->onboarding_completed_at !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=6000,max_height=6000'],
            'user_id' => ['missing'], 'profile_id' => ['missing'], 'photo_path' => ['missing'],
        ];
    }
}

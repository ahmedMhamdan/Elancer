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

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return parent::rules() + [
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'company' => ['sometimes', 'nullable', 'string', 'max:120'],
            'skills' => ['sometimes', 'nullable', 'array', 'list', 'max:15'],
            'skills.*' => ['required', 'string', 'max:50', 'distinct:ignore_case'],
            'photo_path' => ['missing'],
        ];
    }
}

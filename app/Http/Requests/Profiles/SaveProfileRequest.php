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

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['skills.max' => __('validation.max.array')] + parent::messages();
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return parent::rules() + [
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'company' => ['sometimes', 'nullable', 'string', 'max:120'],
            'skills' => ['sometimes', 'nullable', 'array', 'list', 'max:15'],
            'skills.*' => ['required', 'string', 'max:50', 'distinct:ignore_case', 'exists:skills,name'],
            'photo_path' => ['missing'],
            'availability' => ['sometimes', 'required', 'in:available,busy'],
            'professional_links' => ['sometimes', 'array', 'list', 'max:5'],
            'professional_links.*' => ['array:label,url'],
            'professional_links.*.label' => ['required', 'string', 'max:80'],
            'professional_links.*.url' => ['required', 'url:https', 'max:2000'],
        ];
    }
}

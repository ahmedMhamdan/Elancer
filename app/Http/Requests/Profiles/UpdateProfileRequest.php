<?php

namespace App\Http\Requests\Profiles;

use App\Models\Profile;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $profile = $this->route('profile');

        return $profile instanceof Profile
            && $this->user()?->can('update', $profile) === true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'headline' => ['sometimes', 'nullable', 'string', 'max:120'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            // These fields are controlled by the server, including when submitted as null.
            'user_id' => ['missing'],
            'published_at' => ['missing'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'string' => __('profiles.validation.string'),
            'max' => __('profiles.validation.max'),
            'missing' => __('profiles.validation.missing'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'headline' => __('profiles.fields.headline'),
            'bio' => __('profiles.fields.bio'),
            'location' => __('profiles.fields.location'),
            'user_id' => __('profiles.fields.user_id'),
            'published_at' => __('profiles.fields.published_at'),
        ];
    }
}

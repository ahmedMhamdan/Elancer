<?php

namespace App\Http\Requests;

use App\Enums\WorkspaceRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canParticipateInMarketplace() === true;
    }

    protected function prepareForValidation(): void
    {
        $skills = $this->input('skills');

        if (is_array($skills)) {
            $this->merge(['skills' => array_map(
                fn ($skill) => is_string($skill) ? trim($skill) : $skill,
                $skills,
            )]);
        }
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(WorkspaceRole::class)],
            'name' => ['required', 'string', 'max:255'],
            'headline' => ['exclude_unless:role,freelancer', 'required', 'string', 'max:120'],
            'bio' => ['required', 'string', 'max:5000'],
            'skills' => ['exclude_unless:role,freelancer', 'required', 'array', 'list', 'min:1', 'max:15'],
            'skills.*' => ['required', 'string', 'max:50', 'distinct:ignore_case'],
            'company' => ['exclude_unless:role,client', 'nullable', 'string', 'max:120'],
            'country' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=6000,max_height=6000'],
            'user_id' => ['missing'],
            'profile_id' => ['missing'],
            'workspace_role' => ['missing'],
            'onboarding_completed_at' => ['missing'],
            'published_at' => ['missing'],
            'photo_path' => ['missing'],
            'status' => ['missing'],
            'is_admin' => ['missing'],
        ];
    }
}

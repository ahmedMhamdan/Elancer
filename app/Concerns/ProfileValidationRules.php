<?php

namespace App\Concerns;

use App\Actions\Demo\DemoWorld;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, ValidationRule|Closure|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, ValidationRule|Closure|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
            // Plan 027: the demo accounts' address domain is theirs alone, so no member can join the demo by choosing an address.
            function (string $attribute, mixed $value, Closure $fail) use ($userId): void {
                if (is_string($value) && DemoWorld::address($value) && ! ($userId !== null && User::query()->whereKey($userId)->where('email', $value)->exists())) {
                    $fail(__('This email address cannot be used here.'));
                }
            },
        ];
    }
}

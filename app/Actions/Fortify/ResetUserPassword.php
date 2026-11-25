<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        DB::transaction(function () use ($user, $input): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($locked->email !== $user->email || $locked->recovery_generation !== $user->recovery_generation) {
                throw ValidationException::withMessages(['email' => __('passwords.token')]);
            }
            $locked->forceFill([
                'password' => $input['password'],
                'remember_token' => Str::random(60),
                'recovery_generation' => (string) Str::uuid(),
                'pending_email' => null,
                'email_change_hash' => null,
            ])->save();
            $user->refresh();
        });
    }
}

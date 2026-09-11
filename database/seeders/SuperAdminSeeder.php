<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = mb_strtolower(trim((string) config('super_admin.email')));
        if ($email === '') {
            return;
        }

        $hash = (string) config('super_admin.password_hash');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || password_get_info($hash)['algoName'] === 'unknown') {
            throw new RuntimeException('Configure a valid ELANCER_SUPER_ADMIN_EMAIL and password hash before seeding.');
        }

        DB::transaction(function () use ($email, $hash): void {
            // Never overwrite credentials or promote an existing account through seeding.
            // Existing accounts must use the explicit app:make-super-admin command.
            if (User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
                return;
            }

            $user = new User;
            $user->forceFill([
                'name' => 'Super admin',
                'email' => $email,
                'password' => $hash,
                'email_verified_at' => now(),
                'is_admin' => true,
                'is_super_admin' => true,
            ])->save();

            DB::table('admin_access_changes')->insert([
                'actor_id' => null,
                'target_id' => $user->id,
                'actor_email' => null,
                'target_email' => $user->email,
                'from_role' => 'none',
                'to_role' => 'super_admin',
                'reason' => 'Explicit super-admin seed from private bootstrap configuration.',
                'created_at' => now(),
            ]);
        });
    }
}

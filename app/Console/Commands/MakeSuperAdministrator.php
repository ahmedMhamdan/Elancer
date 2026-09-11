<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MakeSuperAdministrator extends Command
{
    protected $signature = 'app:make-super-admin {email : Existing verified account email}';

    protected $description = 'Explicitly grant super-admin access to an existing active, verified account';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));

        return DB::transaction(function () use ($email): int {
            $user = User::whereRaw('LOWER(email) = ?', [$email])->lockForUpdate()->first();
            if (! $user || ! $user->canParticipateInMarketplace()) {
                $this->error('An existing active, email-verified account is required. No account was changed.');

                return self::FAILURE;
            }

            if ($user->is_super_admin) {
                $this->info('This account is already a super admin.');

                return self::SUCCESS;
            }

            DB::table('admin_access_changes')->insert([
                'actor_id' => null,
                'target_id' => $user->id,
                'actor_email' => null,
                'target_email' => $user->email,
                'from_role' => $user->is_admin ? 'admin' : 'member',
                'to_role' => 'super_admin',
                'reason' => 'Explicit super-admin bootstrap through the application console.',
                'created_at' => now(),
            ]);
            $user->forceFill(['is_super_admin' => true, 'is_admin' => true])->save();

            $this->info('Super-admin access granted. Confirm 2FA and complete a login challenge before managing admins.');

            return self::SUCCESS;
        });
    }
}

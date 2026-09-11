<?php

namespace App\Actions;

use App\Models\User;
use App\Policies\AdministratorPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateAdministrator
{
    public function __invoke(User $actor, User $target, bool $isAdmin, string $reason): void
    {
        DB::transaction(function () use ($actor, $target, $isAdmin, $reason): void {
            // Lock in a consistent order and recheck permissions against fresh rows.
            $users = User::whereKey([$actor->id, $target->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $freshActor = $users->get($actor->id);
            $freshTarget = $users->get($target->id);
            abort_unless($freshActor && $freshTarget && app(AdministratorPolicy::class)->update($freshActor, $freshTarget), 403);

            if ($isAdmin && ! $freshTarget->canParticipateInMarketplace()) {
                throw ValidationException::withMessages([
                    'is_admin' => $freshActor->locale === 'ar'
                        ? 'يجب أن يكون الحساب نشطاً وبريده مؤكداً قبل منحه صلاحية الإدارة.'
                        : 'The account must be active and email-verified before receiving admin access.',
                ]);
            }

            if ($freshTarget->is_admin === $isAdmin) {
                return;
            }

            DB::table('admin_access_changes')->insert([
                'actor_id' => $freshActor->id,
                'target_id' => $freshTarget->id,
                'actor_email' => $freshActor->email,
                'target_email' => $freshTarget->email,
                'from_role' => $freshTarget->is_admin ? 'admin' : 'member',
                'to_role' => $isAdmin ? 'admin' : 'member',
                'reason' => $reason,
                'created_at' => now(),
            ]);
            $freshTarget->forceFill(['is_admin' => $isAdmin])->save();
        });
    }
}

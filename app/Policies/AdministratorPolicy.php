<?php

namespace App\Policies;

use App\Models\User;

class AdministratorPolicy
{
    public function manage(User $actor): bool
    {
        return $actor->is_super_admin
            && $actor->canParticipateInMarketplace()
            && $actor->two_factor_secret !== null
            && $actor->two_factor_confirmed_at !== null;
    }

    public function update(User $actor, User $target): bool
    {
        return $this->manage($actor) && ! $target->is_super_admin && $actor->id !== $target->id;
    }
}

<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdministrator() && $user->canParticipateInMarketplace()
            && $user->two_factor_secret !== null && $user->two_factor_confirmed_at !== null;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Category $category): bool
    {
        return $this->viewAny($user) && ! $category->trashed();
    }

    public function delete(User $user, Category $category): bool
    {
        return $this->update($user, $category);
    }

    public function restore(User $user, Category $category): bool
    {
        return $this->viewAny($user) && $category->trashed();
    }

    public function forceDelete(User $user, Category $category): bool
    {
        return $this->viewAny($user) && $category->trashed();
    }
}

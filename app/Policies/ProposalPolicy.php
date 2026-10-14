<?php

namespace App\Policies;

use App\Models\Proposal;
use App\Models\User;

/** Plan 028: through the API a proposal belongs to its author alone, whatever the token may do. */
class ProposalPolicy
{
    public function view(User $user, Proposal $proposal): bool
    {
        return $user->id === $proposal->user_id;
    }
}

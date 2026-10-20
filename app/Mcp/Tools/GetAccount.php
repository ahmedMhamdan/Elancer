<?php

namespace App\Mcp\Tools;

use App\Actions\Proposals\ProposalRules;
use App\Mcp\AgentAccess;
use App\Models\Profile;
use App\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/** Plan 033: the API's /me (plan 028), so an agent can tell why something is refused. */
#[Name('get_account')]
#[Description('Who this connection speaks for: name, email, language, account status, whether the member may apply to projects (an active account, finished onboarding and a published freelancer profile), and what this connection may do. Call this first.')]
#[IsReadOnly]
class GetAccount extends ElancerTool
{
    protected function answer(Request $request, User $user): array
    {
        $published = Profile::query()->publiclyVisible()->where('user_id', $user->id)->exists();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'locale' => $user->preferredLocale(),
            'status' => $user->status->value,
            'can_apply' => ProposalRules::eligible($user) && $published,
            'profile_published' => $published,
            'connection' => AgentAccess::describe($user),
            'payments' => 'Elancer runs payments in test mode: no real money moves.',
        ];
    }
}

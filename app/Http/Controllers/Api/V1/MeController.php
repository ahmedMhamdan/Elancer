<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Proposals\ProposalRules;
use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Plan 028: who the token belongs to and what it may do, so a program can tell why something is refused. */
class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user->currentAccessToken();
        $published = Profile::query()->publiclyVisible()->where('user_id', $user->id)->exists();

        return response()->json(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'locale' => $user->preferredLocale(),
            'status' => $user->status->value,
            // Applying asks for an active account, finished onboarding and a published freelancer profile (Q34).
            'can_apply' => ProposalRules::eligible($user) && $published,
            'profile_published' => $published,
            'token' => ['name' => $token->name, 'abilities' => $token->abilities ?? [], 'expires_at' => $token->expires_at?->toIso8601String()],
        ]]);
    }
}

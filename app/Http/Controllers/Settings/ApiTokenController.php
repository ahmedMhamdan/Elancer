<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Demo\DemoWorld;
use App\Http\Controllers\Controller;
use App\Http\Responses\OAuthConsentPage;
use App\Mcp\AgentAccess;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passport\Token;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Plan 028 (plan 003 section 6): a member's own API tokens. Each has a name, the abilities it was given and
 * an expiry of at most ninety days. The secret is the answer to the request that creates the token and is
 * kept nowhere else: not in the session, not in the page's saved state, and in the database only as a hash.
 */
class ApiTokenController extends Controller
{
    /** Plan 033 adds hiring:read, a client's own projects and the proposals they received, for AI agents. */
    public const ABILITIES = ['projects:read', 'proposals:read', 'proposals:write', 'hiring:read'];

    public const EXPIRY_DAYS = [7, 30, 60, 90];

    public const DEFAULT_EXPIRY_DAYS = 30;

    /** Expired tokens count too, so their owner removes them and the list stays readable. */
    public const LIMIT = 10;

    public function index(Request $request): Response
    {
        return Inertia::render('settings/tokens', [
            'tokens' => $request->user()->tokens()->orderByDesc('id')->get()->map(fn (PersonalAccessToken $token): array => $this->details($token))->all(),
            'abilities' => self::ABILITIES,
            'expiryDays' => self::EXPIRY_DAYS,
            'defaultExpiryDays' => self::DEFAULT_EXPIRY_DAYS,
            'limit' => self::LIMIT,
            'allowed' => $this->allowed($request->user()),
            'endpoint' => url('/api/v1'),
        ]);
    }

    /** Plan 033, its own tab since plan 032 part 9: the MCP address and the apps connected through Connect. */
    public function agents(Request $request): Response
    {
        return Inertia::render('settings/agents', [
            'mcpEndpoint' => route('mcp'),
            'connections' => AgentAccess::connections($request->user()->id)->map(function (Collection $tokens, int|string $client): array {
                $app = $tokens->first()?->client;

                return [
                    'id' => (string) $client,
                    'name' => $app?->name,
                    'returnsTo' => OAuthConsentPage::returnsTo($app),
                    'connected_at' => $app?->created_at?->toIso8601String(),
                    'renewed_at' => $tokens->max('created_at')?->toIso8601String(),
                    'ends_at' => $tokens->map(fn (Token $token) => $token->refreshToken->expires_at ?? $token->expires_at)->filter()->max()?->toIso8601String(),
                ];
            })->values()->all(),
            'connectionLimit' => AgentAccess::CONNECTIONS,
        ]);
    }

    /** Plan 033: the app's tokens end at once; its next call is refused and it must ask to connect again. */
    public function disconnect(Request $request, string $client): RedirectResponse
    {
        abort_unless(AgentAccess::connections($request->user()->id)->has($client), 404);
        AgentAccess::disconnect($request->user()->id, $client);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('App disconnected.')]);

        return back();
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($this->allowed($request->user()), 403, __('This account cannot create API tokens.'));
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['required', 'string', 'distinct', Rule::in(self::ABILITIES)],
            'expires_in_days' => ['required', 'integer', Rule::in(self::EXPIRY_DAYS)],
        ]);
        $created = DB::transaction(function () use ($request, $data) {
            // Two requests at once cannot both take the last place.
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            if ($user->tokens()->count() >= self::LIMIT) {
                throw ValidationException::withMessages(['name' => __('This account already has the most tokens it can hold. Revoke one first.')]);
            }

            return $user->createToken(trim($data['name']), array_values(array_intersect(self::ABILITIES, $data['abilities'])), now()->addDays((int) $data['expires_in_days']));
        }, 3);

        return response()->json(['token' => $created->plainTextToken, 'item' => $this->details($created->accessToken)], 201)->header('Cache-Control', 'no-store');
    }

    public function destroy(Request $request, int $token): RedirectResponse
    {
        // Another account's token does not exist here.
        $request->user()->tokens()->whereKey($token)->firstOrFail()->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Token revoked.')]);

        return back();
    }

    /** Plan 003 section 10: a prepared demo account never issues a token, however it was entered. */
    private function allowed(User $user): bool
    {
        return $user->canParticipateInMarketplace() && ! DemoWorld::holds($user->id);
    }

    /** @return array<string, mixed> */
    private function details(PersonalAccessToken $token): array
    {
        return [
            'id' => $token->getKey(),
            'name' => $token->name,
            'abilities' => $token->abilities ?? [],
            'created_at' => $token->created_at?->toIso8601String(),
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'expires_at' => $token->expires_at?->toIso8601String(),
            'expired' => $token->expires_at !== null && $token->expires_at->isPast(),
        ];
    }
}

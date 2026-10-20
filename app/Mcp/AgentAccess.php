<?php

namespace App\Mcp;

use App\Actions\Demo\DemoWorld;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\AccessToken;
use Laravel\Passport\AuthCode;
use Laravel\Passport\Client;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Plan 033: who an MCP call speaks for and what it may do. An API token (plan 028) may use the tools its
 * abilities name. An app connected through Connect (OAuth) carries laravel/mcp's one scope and may use
 * every tool; the member saw the whole list when approving it.
 */
final class AgentAccess
{
    /** At most this many apps connected to one account at a time, as with API tokens. */
    public const CONNECTIONS = 10;

    public static function allows(mixed $user, ?string $ability): bool
    {
        if (! $user instanceof User) {
            return false;
        }
        $token = $user->currentAccessToken();
        if ($token instanceof AccessToken) {
            return $token->can(Registrar::OAUTH_SCOPE);
        }
        if ($token instanceof PersonalAccessToken) {
            return $ability === null || $token->can($ability);
        }

        return false;
    }

    /** One counter per token, so one agent cannot use up another's allowance. */
    public static function key(User $user): string
    {
        $token = $user->currentAccessToken();

        return match (true) {
            $token instanceof AccessToken => 'oauth:'.($token->oauth_access_token_id ?? 'u'.$user->id),
            $token instanceof PersonalAccessToken => 'token:'.$token->getKey(),
            default => 'user:'.$user->id,
        };
    }

    /**
     * What the caller sees about its own connection: the token's name and abilities, or the connected app.
     *
     * @return array<string, mixed>
     */
    public static function describe(User $user): array
    {
        $token = $user->currentAccessToken();
        if ($token instanceof AccessToken) {
            $client = Client::query()->find($token->oauth_client_id);

            return ['kind' => 'connected_app', 'name' => $client?->name, 'may_use' => 'every tool'];
        }
        if ($token instanceof PersonalAccessToken) {
            return ['kind' => 'api_token', 'name' => $token->name, 'abilities' => $token->abilities ?? [], 'expires_at' => $token->expires_at?->toIso8601String()];
        }

        return ['kind' => 'none'];
    }

    /**
     * Why this account may not approve a new app, or null when it may. Creating an API token asks the same.
     */
    public static function refusal(User $user, ?string $client): ?string
    {
        if (DemoWorld::holds($user->id)) {
            return __('Demo accounts cannot connect AI agents. Create your own account to connect one.');
        }
        if (! $user->canParticipateInMarketplace()) {
            return __('This account cannot connect AI agents right now.');
        }
        $others = self::connections($user->id)->keys()->reject(fn (int|string $id) => (string) $id === $client);
        if ($others->count() >= self::CONNECTIONS) {
            return __('This account already has the most connected apps it can hold. Disconnect one first.');
        }

        return null;
    }

    /**
     * The apps connected to an account, by client id: each client with a token that still works or can still be renewed.
     *
     * @return Collection<array-key, EloquentCollection<int, Token>>
     */
    public static function connections(int $user): Collection
    {
        return Token::query()->where('user_id', $user)->where('revoked', false)
            ->where(fn (Builder $query) => $query->where('expires_at', '>', now())
                ->orWhereHas('refreshToken', fn (Builder $refresh) => $refresh->where('revoked', false)->where('expires_at', '>', now())))
            ->with(['client', 'refreshToken'])->orderByDesc('created_at')->get()
            ->groupBy(fn (Token $token): string => (string) $token->client_id);
    }

    /** Disconnecting an app ends its tokens at once: the next call with one is refused. */
    public static function disconnect(int $user, string $client): void
    {
        $tokens = Token::query()->where('user_id', $user)->where('client_id', $client)->pluck('id');
        Token::query()->whereKey($tokens)->update(['revoked' => true]);
        RefreshToken::query()->whereIn('access_token_id', $tokens)->update(['revoked' => true]);
        AuthCode::query()->where('user_id', $user)->where('client_id', $client)->update(['revoked' => true]);
    }

    /** An account's OAuth tokens and codes go with it; their tables have no foreign key to do that. */
    public static function forget(int $user): void
    {
        $tokens = Token::query()->where('user_id', $user)->pluck('id');
        RefreshToken::query()->whereIn('access_token_id', $tokens)->delete();
        Token::query()->whereKey($tokens)->delete();
        AuthCode::query()->where('user_id', $user)->delete();
    }
}

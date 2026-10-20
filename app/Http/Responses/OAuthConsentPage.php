<?php

namespace App\Http\Responses;

use App\Mcp\AgentAccess;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Passport\Client;
use LogicException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Plan 033: the page an app's Connect button opens, in Elancer's own form and both languages. It names the
 * app and where approving returns to (an app names itself, so the destination is what can be trusted),
 * the account, and what the app may do. The form posts to Passport's approve and deny routes.
 */
final class OAuthConsentPage
{
    /** @param array<string, mixed> $parameters What Passport hands its authorization view. */
    public static function render(array $parameters): SymfonyResponse
    {
        ['client' => $client, 'user' => $user, 'request' => $request, 'authToken' => $authToken] = $parameters + ['client' => null, 'user' => null, 'request' => null, 'authToken' => null];
        if (! $client instanceof Client || ! $user instanceof User || ! $request instanceof Request || ! is_string($authToken)) {
            throw new LogicException('Passport passed an authorization request this page cannot show.');
        }
        // Passport has already checked the address against the app's registered ones.
        $requested = $request->query('redirect_uri');

        return Inertia::render('oauth/authorize', [
            'app' => ['name' => $client->name, 'returnsTo' => self::returnsTo($client, is_string($requested) ? $requested : null)],
            'account' => ['name' => $user->name, 'email' => $user->email],
            'refusal' => AgentAccess::refusal($user, (string) $client->getKey()),
            'form' => [
                'action' => route('passport.authorizations.approve'),
                'csrf' => csrf_token(),
                'state' => (string) $request->query('state', ''),
                'clientId' => (string) $client->getKey(),
                'authToken' => $authToken,
            ],
        ])->toResponse($request);
    }

    /** Where approving the app returns to: the address it asked for, or else the first one it registered. */
    public static function returnsTo(?Client $client, ?string $requested = null): string
    {
        $registered = $client?->getAttribute('redirect_uris');
        $first = is_array($registered) && is_string($registered[0] ?? null) ? $registered[0] : '';

        return self::destination($requested ?? $first);
    }

    /** The host an approval returns to, with its port on a local address, or the scheme of a desktop app. */
    public static function destination(string $uri): string
    {
        $parts = parse_url($uri);
        $host = is_array($parts) ? ($parts['host'] ?? '') : '';
        if ($host === '') {
            return $uri;
        }
        $scheme = $parts['scheme'] ?? '';

        return in_array($scheme, ['http', 'https'], true) ? $host.(isset($parts['port']) ? ':'.$parts['port'] : '') : $scheme.'://'.$host;
    }
}

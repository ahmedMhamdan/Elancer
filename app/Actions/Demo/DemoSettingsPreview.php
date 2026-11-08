<?php

namespace App\Actions\Demo;

use App\Http\Controllers\Settings\ApiTokenController;
use App\Mcp\AgentAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/** Read-only settings without invoking credential actions or exposing shared security metadata. */
class DemoSettingsPreview
{
    public static function respond(Request $request): Response
    {
        $props = [];
        switch ($request->path()) {
            case 'settings':
                return redirect()->route('profile.edit');
            case 'settings/profile':
                $page = 'profile';
                $props = ['mustVerifyEmail' => false, 'status' => null];
                break;
            case 'settings/security':
                $page = 'security';
                $props = ['hasPassword' => true, 'connectedProviders' => [], 'socialProviders' => ['google' => false, 'github' => false],
                    'canManageTwoFactor' => true, 'canManagePasskeys' => true, 'passkeys' => [], 'twoFactorEnabled' => false,
                    'requiresConfirmation' => true, 'passwordRules' => Password::defaults()->toPasswordRulesString()];
                break;
            case 'settings/appearance':
                $page = 'appearance';
                break;
            case 'settings/notifications':
                $page = 'notifications';
                $props = ['preferences' => ['contracts' => true, 'hiring' => false, 'messages' => false]];
                break;
            case 'settings/tokens':
                $page = 'tokens';
                $props = ['tokens' => [], 'abilities' => ApiTokenController::ABILITIES, 'expiryDays' => ApiTokenController::EXPIRY_DAYS,
                    'defaultExpiryDays' => ApiTokenController::DEFAULT_EXPIRY_DAYS, 'limit' => ApiTokenController::LIMIT,
                    'allowed' => false, 'endpoint' => url('/api/v1')];
                break;
            case 'settings/agents':
                $page = 'agents';
                $props = ['connections' => [], 'connectionLimit' => AgentAccess::CONNECTIONS, 'mcpEndpoint' => route('mcp')];
                break;
            default:
                abort(404);
        }

        return Inertia::render('settings/'.$page, $props)->toResponse($request);
    }
}

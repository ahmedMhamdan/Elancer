<?php

namespace App\Http\Controllers;

use App\Actions\Auth\DemoAccess;
use App\Enums\WorkspaceRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Plan 025: a visitor who does not want to register enters a prepared account from the login or
 * register page. No password is asked for or known; the session is marked and stays view-only.
 */
class DemoSessionController extends Controller
{
    public function store(Request $request, string $role): RedirectResponse
    {
        $user = DemoAccess::account($role);
        if ($user === null) {
            throw ValidationException::withMessages(['demo' => __('The demo is not available right now. You can still create a free account.')]);
        }
        // Each button opens its own workspace, whatever the shared account was last left in.
        $workspace = WorkspaceRole::from($role);
        if ($user->workspace_role !== $workspace) {
            $user->forceFill(['workspace_role' => $workspace])->save();
        }
        // The visitor keeps the language they were reading in; the account's saved one belongs to nobody.
        $locale = app()->getLocale();
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put([DemoAccess::SESSION_KEY => true, 'locale' => $locale]);

        return redirect()->route('dashboard');
    }

    /** "Create your own account": ends the demo session and opens registration. */
    public function destroy(Request $request): RedirectResponse
    {
        abort_unless(DemoAccess::active($request), 404);
        $locale = app()->getLocale();
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put('locale', $locale);

        return redirect()->route('register');
    }
}

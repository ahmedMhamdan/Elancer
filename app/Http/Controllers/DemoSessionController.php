<?php

namespace App\Http\Controllers;

use App\Actions\Auth\DemoAccess;
use App\Actions\Demo\DemoBaseline;
use App\Enums\WorkspaceRole;
use App\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Plans 025 and 027: a visitor who does not want to register enters a prepared account from the login
 * or register page. No password is asked for or known; the session is marked, and RestrictDemoSession
 * decides what it may do.
 */
class DemoSessionController extends Controller
{
    public function store(Request $request, string $role): RedirectResponse
    {
        $user = DemoAccess::account($role);
        if ($user === null) {
            throw ValidationException::withMessages(['demo' => __('The demo is not available right now. You can still create a free account.')]);
        }
        // Plan 027: a demo left changed and quiet for a while starts this visit as it was saved.
        DemoBaseline::restoreWhenDue();
        $this->workspace($user, $role);
        // The visitor keeps the language they were reading in; the account's saved one belongs to nobody.
        $locale = app()->getLocale();
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put([DemoAccess::SESSION_KEY => true, 'locale' => $locale]);

        return redirect()->route('dashboard');
    }

    /** Plan 027: moves a demo visitor to the other prepared account, so one person can play both sides of a hire. */
    public function switch(Request $request): RedirectResponse
    {
        abort_unless(DemoAccess::active($request), 404);
        $role = DemoAccess::other($request->user());
        $user = $role === null ? null : DemoAccess::account($role);
        $guard = Auth::guard('web');
        if ($role === null || $user === null || ! $guard instanceof SessionGuard) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('The other demo account is not available right now.')]);

            return back(303, [], route('dashboard'));
        }
        $this->workspace($user, $role);
        // The same visitor in the same demo session: only the account changes. The session keeps its number, because
        // a new one would answer a page this visitor opens while the switch is under way as a stranger and sign them out.
        $request->session()->put($guard->getName(), $user->getAuthIdentifier());
        $request->session()->forget('auth.password_confirmed_at');
        $guard->setUser($user);

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

    /** Each button opens its own workspace, whatever the shared account was last left in. */
    private function workspace(User $user, string $role): void
    {
        $workspace = WorkspaceRole::from($role);
        if ($user->workspace_role !== $workspace) {
            $user->forceFill(['workspace_role' => $workspace])->save();
        }
    }
}

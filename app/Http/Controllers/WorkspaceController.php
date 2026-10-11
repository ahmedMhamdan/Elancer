<?php

namespace App\Http\Controllers;

use App\Enums\WorkspaceRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Q25: one account with a Client and a Freelancer workspace. The choice decides what the member
 * is shown; every action keeps its own server rule, so nothing here grants or removes access.
 */
class WorkspaceController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate(['workspace' => ['required', Rule::enum(WorkspaceRole::class)]]);
        $workspace = WorkspaceRole::from($data['workspace']);
        if ($request->user()->workspace_role !== $workspace) {
            $request->user()->forceFill(['workspace_role' => $workspace])->save();
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => $workspace === WorkspaceRole::Client
            ? __('You are now in the Client workspace.')
            : __('You are now in the Freelancer workspace.')]);

        return back(fallback: route('dashboard'));
    }
}

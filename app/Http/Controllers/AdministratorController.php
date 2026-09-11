<?php

namespace App\Http\Controllers;

use App\Actions\UpdateAdministrator;
use App\Http\Requests\UpdateAdministratorRequest;
use App\Models\User;
use App\Policies\AdministratorPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdministratorController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(app(AdministratorPolicy::class)->manage($request->user()), 403);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = User::query();
        $search = $filters['q'] ?? '';
        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('email', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%');
            });
        }

        return Inertia::render('admin/administrators/index', [
            // Explicit selection prevents sharing auth secrets or unrelated profile data.
            'users' => $query->orderByDesc('is_super_admin')->orderByDesc('is_admin')->orderBy('id')
                ->paginate(10, ['id', 'name', 'email', 'status', 'email_verified_at', 'is_admin', 'is_super_admin'])
                ->withQueryString(),
            'search' => $search,
            'notice' => $request->session()->get('admin_access_notice'),
        ]);
    }

    public function edit(Request $request, User $user): Response
    {
        abort_unless(app(AdministratorPolicy::class)->update($request->user(), $user), 403);

        return Inertia::render('admin/administrators/edit', [
            'account' => $user->only(['id', 'name', 'email', 'is_admin', 'status', 'email_verified_at']),
        ]);
    }

    public function update(UpdateAdministratorRequest $request, User $user, UpdateAdministrator $update): RedirectResponse
    {
        $update($request->user(), $user, $request->boolean('is_admin'), $request->validated('reason'));

        return to_route('admin.administrators.index')->with('admin_access_notice', true);
    }
}

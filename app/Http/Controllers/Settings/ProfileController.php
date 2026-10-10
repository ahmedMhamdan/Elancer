<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\PortfolioCase;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->is_super_admin) {
            throw ValidationException::withMessages([
                'password' => __('Super-admin accounts cannot be deleted from account settings.'),
            ]);
        }

        $photoPath = $user->profile()->first()?->photo_path;
        $portfolio = PortfolioCase::query()->where('user_id', $user->id)->pluck('id');
        DB::transaction(function () use ($user): void {
            $locked = $user->newQuery()->lockForUpdate()->findOrFail($user->id);
            $locked->delete();
        }, 3);
        Auth::logout();

        if ($photoPath !== null) {
            Storage::disk('uploads')->delete($photoPath);
        }
        // The case rows went with the account; their image files are removed here.
        foreach ($portfolio as $case) {
            rescue(fn () => Storage::disk('uploads')->deleteDirectory('portfolio-images/'.$case));
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

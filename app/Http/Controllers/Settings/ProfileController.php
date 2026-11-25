<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\Conversation;
use App\Models\PortfolioCase;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use App\Notifications\VerifyReplacementEmail;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

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
    public function update(ProfileUpdateRequest $request): HttpResponse
    {
        $user = $request->user();
        $email = $request->validated('email');
        if ($email !== $user->email) {
            return app(RequirePassword::class)->handle($request, function () use ($request, $user, $email): RedirectResponse {
                $token = Str::random(64);
                $updated = User::query()->whereKey($user->id)->where('email', $user->email)
                    ->where('recovery_generation', $user->recovery_generation)
                    ->update(['name' => $request->validated('name'), 'pending_email' => $email, 'email_change_hash' => hash('sha256', $token)]);
                abort_unless($updated === 1, 409);
                $path = URL::temporarySignedRoute('profile.email.verify', now()->addHour(), ['token' => $token], absolute: false);
                $url = rtrim((string) config('app.url'), '/').$path;
                Notification::route('mail', $email)->notify((new VerifyReplacementEmail($url))->locale($user->preferredLocale()));
                Inertia::flash('toast', ['type' => 'success', 'message' => __('Check your new mailbox to confirm the change. Your current email still works.')]);

                return to_route('profile.edit');
            });
        }
        $user->fill(['name' => $request->validated('name')])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    public function verifyEmail(Request $request, string $token): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $token): void {
                $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
                abort_unless($user->pending_email !== null && hash_equals((string) $user->email_change_hash, hash('sha256', $token)), 403);
                Password::broker()->deleteToken($user);
                $user->forceFill(['email' => $user->pending_email, 'email_verified_at' => now(), 'pending_email' => null, 'email_change_hash' => null])->save();
                Password::broker()->deleteToken($user);
            });
        } catch (UniqueConstraintViolationException) {
            return to_route('profile.edit')->withErrors(['email' => __('validation.unique', ['attribute' => __('Email address')])]);
        }
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
        $briefs = Project::withTrashed()->where('user_id', $user->id)->whereHas('attachments')->pluck('id');
        // Plan 030: the member's proposals and the proposals to their projects go with the account, and so do their conversations.
        $proposals = Proposal::query()->whereHas('files')->where(fn ($q) => $q->where('user_id', $user->id)
            ->orWhereIn('project_id', Project::withTrashed()->where('user_id', $user->id)->select('id')))->pluck('id');
        $conversations = Conversation::query()->whereHas('messages.files')->where(fn ($q) => $q->where('client_id', $user->id)->orWhere('freelancer_id', $user->id))->pluck('id');
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
        // Q11: the same for the files attached to the member's briefs.
        foreach ($briefs as $brief) {
            rescue(fn () => Storage::disk('uploads')->deleteDirectory('project-attachments/'.$brief));
        }
        foreach ($proposals as $proposal) {
            rescue(fn () => Storage::disk('uploads')->deleteDirectory('proposal-files/'.$proposal));
        }
        foreach ($conversations as $conversation) {
            rescue(fn () => Storage::disk('uploads')->deleteDirectory('message-files/'.$conversation));
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

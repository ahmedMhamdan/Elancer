<?php

namespace App\Http\Controllers;

use App\Actions\PrepareProfilePhoto;
use App\Http\Requests\Profiles\UpdateProfilePhotoRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class UpdateProfilePhotoController extends Controller
{
    public function __invoke(UpdateProfilePhotoRequest $request, PrepareProfilePhoto $prepare): RedirectResponse|JsonResponse
    {
        $bytes = $prepare->handle($request->file('photo'));
        $newPath = null;
        $oldPath = null;
        try {
            DB::transaction(function () use ($request, $bytes, &$newPath, &$oldPath): void {
                $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
                abort_unless($user->canParticipateInMarketplace(), 403);
                $profile = $user->profile()->firstOrNew();
                $newPath = 'profile-photos/'.$user->id.'/'.Str::uuid().'.jpg';
                if (! Storage::disk('local')->put($newPath, $bytes)) {
                    throw ValidationException::withMessages(['photo' => __('We could not save your photo. Please try again.')]);
                }
                $oldPath = $profile->photo_path;
                $profile->photo_path = $newPath;
                $profile->save();
            });
        } catch (Throwable $exception) {
            if ($newPath !== null) {
                Storage::disk('local')->delete($newPath);
            }
            throw $exception;
        }
        if ($oldPath !== null && $oldPath !== $newPath) {
            try {
                Storage::disk('local')->delete($oldPath);
            } catch (Throwable) {
                report(new RuntimeException('An unused profile photo could not be removed.'));
            }
        }
        if ($request->expectsJson()) {
            return response()->json(['avatar' => route('profile.photo', ['v' => hash('sha256', $newPath)])]);
        }

        return to_route('marketplace-profile.edit');
    }
}

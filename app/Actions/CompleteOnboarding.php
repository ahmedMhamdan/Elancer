<?php

namespace App\Actions;

use App\Enums\WorkspaceRole;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class CompleteOnboarding
{
    /**
     * @param  array{role: string, name: string, bio: string, country: string, city: string, headline?: string, company?: string|null, skills?: list<string>}  $data
     */
    public function handle(User $user, array $data, ?UploadedFile $photo): bool
    {
        $newPhotoPath = null;
        $oldPhotoPath = null;
        $completed = false;

        try {
            DB::transaction(function () use ($user, $data, $photo, &$newPhotoPath, &$oldPhotoPath, &$completed): void {
                // Serialize submissions for this account; only the first completion wins.
                $account = User::query()->lockForUpdate()->findOrFail($user->id);
                abort_unless($account->canParticipateInMarketplace(), 403);

                if ($account->onboarding_completed_at !== null) {
                    return;
                }

                $profile = $account->profile()->firstOrNew();
                $role = WorkspaceRole::from($data['role']);

                if ($photo !== null) {
                    $path = $photo->store('profile-photos/'.$account->id, 'local');

                    if ($path === false) {
                        throw ValidationException::withMessages([
                            'photo' => __('We could not save your photo. Please try again.'),
                        ]);
                    }

                    $newPhotoPath = $path;
                    $oldPhotoPath = $profile->photo_path;
                    $profile->photo_path = $path;
                }

                $profile->headline = $role === WorkspaceRole::Freelancer ? ($data['headline'] ?? null) : null;
                $profile->bio = $data['bio'];
                $profile->country = $data['country'];
                $profile->city = $data['city'];
                $profile->location = $data['city'].', '.$data['country'];
                $profile->company = $role === WorkspaceRole::Client ? ($data['company'] ?? null) : null;
                // Catalog validation precedes the transaction; persist shared tag associations.
                $profile->skills = $role === WorkspaceRole::Freelancer ? ($data['skills'] ?? []) : [];
                $account->profile()->save($profile);
                $profile->syncSkillTags($profile->skills ?? []);

                $account->name = $data['name'];
                $account->workspace_role = $role;
                $account->onboarding_completed_at = now();
                $account->save();
                $completed = true;
            });
        } catch (Throwable $exception) {
            if ($newPhotoPath !== null) {
                $this->deletePhoto($newPhotoPath);
            }

            throw $exception;
        }

        // Remove the old file only after the new profile and account have committed.
        if ($oldPhotoPath !== null && $oldPhotoPath !== $newPhotoPath) {
            $this->deletePhoto($oldPhotoPath);
        }

        return $completed;
    }

    private function deletePhoto(string $path): void
    {
        try {
            if (! Storage::disk('local')->delete($path)) {
                report(new RuntimeException('An unused profile photo could not be removed.'));
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** Q11 and plan 023: a brief's files are managed on the draft only and frozen with the brief at publication. */
class ProjectAttachmentController extends Controller
{
    /**
     * What a page may show of a brief's files: never where they are stored.
     *
     * @return list<array<string, mixed>>
     */
    public static function listing(Project $project): array
    {
        return array_values($project->attachments()->orderBy('id')->get()->map(fn (ProjectAttachment $file) => $file->only(['id', 'name', 'size']))->all());
    }

    /** Q11: the owner always; anyone else needs an active, email-verified account and a project that is on public pages. */
    public static function downloadable(?User $user, Project $project): bool
    {
        if ($user === null) {
            return false;
        }
        if ($user->id === $project->user_id) {
            return $user->keepsExistingAccess();
        }

        return $user->canParticipateInMarketplace() && Project::query()->visible()->whereKey($project->id)->exists();
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->draft($request, $project);
        $wrongType = __('Attach a JPG, PNG, WebP, PDF or TXT file.');
        $request->validate(['file' => ['bail', 'required', 'file', 'max:'.ProjectAttachment::KILOBYTES, 'extensions:'.implode(',', array_keys(ProjectAttachment::TYPES)),
            // The content must be what the name says it is, so a renamed archive or program is refused.
            function (string $attribute, UploadedFile $value, Closure $fail) use ($wrongType): void {
                if (! str_starts_with((string) $value->getMimeType(), ProjectAttachment::TYPES[strtolower($value->getClientOriginalExtension())] ?? '-')) {
                    $fail($wrongType);
                }
            }]], [
                'file.max' => __('Each file can be up to 5 MB.'), 'file.extensions' => $wrongType,
                'file.uploaded' => __('This file could not be uploaded. It may be larger than this server accepts.'),
            ]);
        $file = $request->file('file');
        // Stored before the row lock, so the lock is never held during the transfer.
        $path = 'project-attachments/'.$project->id.'/'.Str::uuid();
        try {
            if (! Storage::disk('uploads')->put($path, $file->getContent())) {
                throw ValidationException::withMessages(['file' => __('We could not save this file. Please try again.')]);
            }
            DB::transaction(function () use ($request, $project, $file, $path): void {
                $locked = Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
                $this->draft($request, $locked);
                if ($locked->attachments()->count() >= ProjectAttachment::LIMIT) {
                    throw ValidationException::withMessages(['file' => __('A brief can carry up to :count files. Remove one to add another.', ['count' => ProjectAttachment::LIMIT])]);
                }
                (new ProjectAttachment)->forceFill(['project_id' => $locked->id, 'name' => Str::limit(basename($file->getClientOriginalName()), 200, ''),
                    'path' => $path, 'mime' => (string) $file->getMimeType(), 'size' => (int) $file->getSize()])->save();
            });
        } catch (Throwable $exception) {
            rescue(fn () => Storage::disk('uploads')->delete($path));
            throw $exception;
        }

        return response()->json(['attachments' => self::listing($project)], 201);
    }

    public function destroy(Request $request, Project $project, ProjectAttachment $attachment): JsonResponse
    {
        $this->draft($request, $project);
        abort_unless($attachment->project_id === $project->id, 404);
        DB::transaction(function () use ($request, $project, $attachment): void {
            $this->draft($request, Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail());
            $attachment->delete();
            DB::afterCommit(fn () => rescue(fn () => Storage::disk('uploads')->delete($attachment->path)));
        });

        return response()->json(['attachments' => self::listing($project)]);
    }

    public function download(Request $request, Project $project, ProjectAttachment $attachment): Response
    {
        abort_unless($attachment->project_id === $project->id, 404);
        $user = $request->user();
        $own = $user->id === $project->user_id && $user->keepsExistingAccess();
        // A draft, a trashed draft and a project hidden by moderation have no files for anyone but their owner.
        abort_unless($own || Project::query()->visible()->whereKey($project->id)->exists(), 404);
        abort_unless($own || $user->canParticipateInMarketplace(), 403);
        abort_unless(Storage::disk('uploads')->exists($attachment->path), 404);

        // Always a download under a neutral type, so a file is never shown or run inside the site.
        return Storage::disk('uploads')->download($attachment->path, $attachment->name, ['Content-Type' => 'application/octet-stream', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** The owner in good standing, and a brief that is still a draft. */
    private function draft(Request $request, Project $project): void
    {
        abort_unless($request->user()->canParticipateInMarketplace() && $request->user()->onboarding_completed_at !== null, 403);
        abort_unless($project->user_id === $request->user()->id, 404);
        abort_unless($project->status === 'draft', 409, __('This project can no longer be changed.'));
    }
}

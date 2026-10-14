<?php

namespace App\Http\Controllers;

use App\Actions\Auth\DemoAccess;
use App\Actions\Demo\DemoWorld;
use App\Actions\Files\AttachedFiles;
use App\Actions\Invitations\InvitationLifecycle;
use App\Actions\Proposals\ProposalRules;
use App\Models\Contract;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\ProposalFile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Plan 030: a proposal's files are added and removed beside its text, while the proposal can be edited.
 * Submit freezes the list into the version the client receives (SaveProposal).
 */
class ProposalFileController extends Controller
{
    /**
     * The files the freelancer is working with: the next version's, never where they are stored.
     *
     * @return list<array<string, mixed>>
     */
    public static function draft(Proposal $proposal): array
    {
        return array_values($proposal->files()->whereNull('removed_at')->orderBy('id')->get()->map(fn (ProposalFile $file) => $file->only(['id', 'name', 'size']))->all());
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $proposal = $this->proposal($request, $project);
        $request->validate(['file' => AttachedFiles::rules()], AttachedFiles::messages('file'));
        $file = $request->file('file');
        AttachedFiles::ensureRoom($request->user()->id, (int) $file->getSize(), 'file');
        // Stored before the row locks, so no lock is held during the transfer.
        $path = AttachedFiles::store($file, 'proposal-files/'.$proposal->id, 'file');
        try {
            DB::transaction(function () use ($request, $project, $file, $path): void {
                User::query()->lockForUpdate()->findOrFail($request->user()->id);
                $locked = $this->proposal($request, Project::query()->lockForUpdate()->findOrFail($project->id), true);
                if ($locked->files()->whereNull('removed_at')->count() >= AttachedFiles::LIMIT) {
                    throw ValidationException::withMessages(['file' => __('A proposal can carry up to :count files. Remove one to add another.', ['count' => AttachedFiles::LIMIT])]);
                }
                AttachedFiles::ensureRoom($request->user()->id, (int) $file->getSize(), 'file');
                (new ProposalFile)->forceFill(['proposal_id' => $locked->id, 'name' => AttachedFiles::name($file), 'path' => $path,
                    'mime' => (string) $file->getMimeType(), 'size' => (int) $file->getSize()])->save();
            });
        } catch (Throwable $exception) {
            rescue(fn () => Storage::disk('uploads')->delete($path));
            throw $exception;
        }

        return response()->json(['files' => self::draft($proposal)], 201);
    }

    /** A file no client has received goes for good; one a client received stays in the history and leaves the next version only. */
    public function destroy(Request $request, Project $project, ProposalFile $file): JsonResponse
    {
        $proposal = $this->proposal($request, $project);
        abort_unless($file->proposal_id === $proposal->id, 404);
        DB::transaction(function () use ($request, $project, $file): void {
            $this->proposal($request, Project::query()->lockForUpdate()->findOrFail($project->id), true);
            $locked = ProposalFile::query()->lockForUpdate()->whereKey($file->id)->whereNull('removed_at')->first();
            abort_if($locked === null, 404);
            if ($locked->submitted_at === null) {
                $locked->delete();
                DB::afterCommit(fn () => rescue(fn () => Storage::disk('uploads')->delete($locked->path)));
            } else {
                $locked->forceFill(['removed_at' => now()])->save();
            }
        });

        return response()->json(['files' => self::draft($proposal)]);
    }

    /** The freelancer downloads any file of their proposal; the client who receives it, any file of a version they received. */
    public function download(Request $request, Proposal $proposal, ProposalFile $file): Response
    {
        abort_unless($file->proposal_id === $proposal->id, 404);
        $user = $request->user();
        $author = $proposal->user_id === $user->id;
        $client = $proposal->project->user_id === $user->id && $proposal->submitted_at !== null && $file->submitted_at !== null
            && ! DemoWorld::trial($proposal->user_id, $proposal->project->user_id);
        abort_unless($author || $client, 404);
        // The proposal page's own rule: Q64 leaves a suspended member what belongs to an existing contract.
        abort_unless(ProposalRules::eligible($user) || ($user->keepsExistingAccess() && Contract::query()->where('proposal_id', $proposal->id)->exists()), 403);
        // Plans 025 and 027: no file leaves storage through an account anyone can open.
        abort_if(DemoAccess::active($request), 403);
        abort_unless(Storage::disk('uploads')->exists($file->path), 404);

        // Always a download under a neutral type, so a file is never shown or run inside the site.
        return Storage::disk('uploads')->download($file->path, $file->name, ['Content-Type' => 'application/octet-stream', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** The freelancer's own proposal on this project, while it can be edited and someone will receive it. */
    private function proposal(Request $request, Project $project, bool $lock = false): Proposal
    {
        $user = $request->user();
        abort_unless(ProposalRules::eligible($user), 403);
        abort_if($project->user_id === $user->id, 403);
        // Plan 027: nobody receives a member's proposal on a sample project, and a demo account never applies to a member's.
        abort_if(DemoWorld::apart($user->id, $project->user_id), 403, __('Nobody receives a proposal on a sample project, so it takes no files.'));
        $proposal = Proposal::query()->where('project_id', $project->id)->where('user_id', $user->id)->when($lock, fn ($q) => $q->lockForUpdate())->first();
        abort_if($proposal === null, 409, __('Save your draft before attaching files.'));
        abort_unless(ProposalRules::editable($project, $proposal), 409, __('This proposal cannot be edited right now.'));
        abort_if(InvitationLifecycle::blocked($project->user_id, $user->id), 403);

        return $proposal;
    }
}

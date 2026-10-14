<?php

namespace App\Actions\Proposals;

use App\Actions\Demo\DemoWorld;
use App\Actions\Invitations\InvitationLifecycle;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\ProposalFile;
use App\Models\User;
use App\Notifications\WorkspaceEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Saves a private draft or submits it. The proposal page and the API (plan 028) both come through here. */
class SaveProposal
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{proposal: Proposal|null, conflict: bool}
     */
    public static function handle(User $actor, Project $project, array $input): array
    {
        abort_unless(ProposalRules::eligible($actor), 403);
        Validator::make($input, ['action' => ['required', Rule::in(['save', 'submit'])], 'version' => ['required', 'integer', 'min:0']])->validate();

        return DB::transaction(function () use ($actor, $project, $input): array {
            $user = $actor->newQuery()->lockForUpdate()->findOrFail($actor->id);
            abort_unless(ProposalRules::eligible($user), 403);
            $locked = Project::query()->lockForUpdate()->findOrFail($project->id);
            abort_if($locked->user_id === $user->id, 403);
            abort_if(ProposalRules::closed($user->id, $locked->user_id), 403, __('Demo accounts and members work apart, so this is not available between them.'));
            $proposal = Proposal::query()->where('project_id', $locked->id)->where('user_id', $user->id)->lockForUpdate()->first();
            abort_unless(ProposalRules::editable($locked, $proposal), 409, __('This proposal cannot be edited right now.'));
            if (($proposal->version ?? 0) !== (int) $input['version']) {
                return ['proposal' => $proposal, 'conflict' => true];
            }
            abort_if(InvitationLifecycle::blocked($locked->user_id, $user->id), 403);
            $submit = $input['action'] === 'submit';
            $presence = $submit ? 'required' : 'nullable';
            $data = Validator::make($input, [
                'price' => [$presence, 'numeric', 'min:1', 'max:1000000', 'decimal:0,2'],
                'duration_days' => [$presence, 'integer', 'min:1', 'max:3650'],
                'message' => [$presence, 'string', 'max:10000', ...($submit ? ['min:20'] : [])],
                'answers' => ['present', 'array', 'list', 'max:3', ...($submit ? ['size:'.count($locked->screening_questions ?? [])] : [])],
                'answers.*' => [$submit ? 'required' : 'nullable', 'string', 'max:3000'],
                'samples' => ['present', 'array', 'list', 'max:3'],
                'samples.*' => ['array:label,url'],
                'samples.*.label' => ['required', 'string', 'max:80'],
                'samples.*.url' => ['required', 'url:https', 'max:2000'],
                'user_id' => ['missing'], 'status' => ['missing'], 'profile_snapshot' => ['missing'], 'client_note' => ['missing'],
            ])->validate();
            $profile = $user->profile;
            if ($submit && ! in_array($proposal?->status, ['submitted', 'reopened'], true)) {
                abort_unless($profile && Profile::query()->publiclyVisible()->whereKey($profile->id)->exists(), 422, __('Publish your freelancer profile before applying.'));
            }
            $data['price'] = isset($data['price']) ? number_format((float) $data['price'], 2, '.', '') : null;
            $data['duration_days'] = isset($data['duration_days']) ? (int) $data['duration_days'] : null;
            $data['answers'] = array_map(fn ($answer) => $answer ?? '', $data['answers']);
            $proposal ??= new Proposal;
            $previousStatus = $proposal->status;
            $proposal->forceFill(['project_id' => $locked->id, 'user_id' => $user->id, 'version' => ($proposal->exists ? $proposal->version : 0) + 1, 'draft' => $data]);
            if ($submit) {
                $first = $proposal->submitted_at === null;
                // Plan 030: the files the freelancer is working with become this version's, for the client and the history alike.
                $files = $proposal->exists ? $proposal->files()->whereNull('removed_at')->orderBy('id')->lockForUpdate()->get() : collect();
                $data['files'] = array_values($files->map(fn (ProposalFile $file) => $file->only(['id', 'name', 'size']))->all());
                ProposalFile::query()->whereIn('id', array_column($data['files'], 'id'))->whereNull('submitted_at')->update(['submitted_at' => now()]);
                $proposal->content = $data;
                $proposal->forceFill(['price' => $data['price'], 'duration_days' => $data['duration_days']]);
                // Retain the exact shared identity; private edits never enter a recipient's view.
                if ($first) {
                    $proposal->profile_snapshot = $profile?->publicDetails();
                }
                $proposal->status = 'submitted';
                $proposal->submitted_at ??= now();
                $proposal->draft = null;
                $proposal->save();
                InvitationLifecycle::accept($proposal);
                // Plan 027: the shared demo account is not told of a member's application.
                if ($first && ! DemoWorld::trial($user->id, $locked->user_id)) {
                    DB::afterCommit(fn () => User::query()->find($locked->user_id)?->notify(new WorkspaceEvent('proposal_received', '/proposals/'.$proposal->id, $locked->title, $user->name)));
                }
                ProposalRules::record($proposal, $user->id, $first ? 'submitted' : (in_array($previousStatus, ['withdrawn', 'reopened'], true) ? 'resubmitted' : 'revised'), $data);
            } else {
                if (! $proposal->exists) {
                    $proposal->status = 'draft';
                }
                $proposal->save();
            }

            return ['proposal' => $proposal, 'conflict' => false];
        });
    }
}

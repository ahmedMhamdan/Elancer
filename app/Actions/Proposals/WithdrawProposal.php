<?php

namespace App\Actions\Proposals;

use App\Actions\Offers\OfferLifecycle;
use App\Models\Offer;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/** The author takes a submitted proposal back. The proposal page and the API (plan 028) both come through here. */
class WithdrawProposal
{
    /** @param array<string, mixed> $input */
    public static function handle(User $actor, Proposal $proposal, array $input): void
    {
        abort_unless(ProposalRules::eligible($actor), 403);
        Validator::make($input, ['version' => ['required', 'integer', 'min:1']])->validate();
        abort_unless($proposal->user_id === $actor->id, 404);
        DB::transaction(function () use ($actor, $proposal, $input): void {
            $project = Project::query()->lockForUpdate()->findOrFail($proposal->project_id);
            $locked = Proposal::query()->lockForUpdate()->findOrFail($proposal->id);
            abort_unless($project->status === 'published' && $locked->version === (int) $input['version'] && in_array($locked->status, ['submitted', 'reopened'], true), 409);
            OfferLifecycle::expire($project->id);
            abort_if(Offer::query()->where('proposal_id', $locked->id)->where('status', 'pending')->exists(), 409, __('Respond to the pending offer before withdrawing this proposal.'));
            $locked->status = 'withdrawn';
            $locked->version++;
            $locked->save();
            ProposalRules::record($locked, $actor->id, 'withdrawn');
        });
    }
}

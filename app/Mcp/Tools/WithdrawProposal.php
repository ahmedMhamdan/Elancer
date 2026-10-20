<?php

namespace App\Mcp\Tools;

use App\Actions\Proposals\WithdrawProposal as WithdrawProposalAction;
use App\Http\Resources\Api\V1\ProposalResource;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

/** Plan 033: the API's POST /me/proposals/{id}/withdraw, through the action the proposal page runs. */
#[Name('withdraw_proposal')]
#[Description('Take back one of the member\'s submitted proposals. Ask the member first. Send its current version. A proposal with a pending final offer cannot be withdrawn until the offer is answered. A withdrawn proposal can be submitted again with save_proposal while the project is open.')]
#[IsDestructive]
class WithdrawProposal extends ElancerTool
{
    protected ?string $ability = 'proposals:write';

    protected bool $writes = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'proposal_id' => $schema->integer()->min(1)->required(),
            'version' => $schema->integer()->min(1)->required()->description('The proposal\'s version as last read.'),
        ];
    }

    protected function answer(Request $request, User $user): array
    {
        $id = (int) $request->validate(['proposal_id' => ['required', 'integer', 'min:1']])['proposal_id'];
        $proposal = Proposal::query()->findOrFail($id);
        abort_unless($user->can('view', $proposal), 404);
        WithdrawProposalAction::handle($user, $proposal, ['version' => $request->get('version')]);

        return (new ProposalResource($proposal->fresh(['project'])))->resolve($this->http());
    }
}

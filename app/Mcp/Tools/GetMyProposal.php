<?php

namespace App\Mcp\Tools;

use App\Actions\Proposals\ProposalRules;
use App\Http\Resources\Api\V1\ProposalResource;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/** Plan 033: the API's /me/proposals/{id}. Someone else's proposal does not exist for the caller. */
#[Name('get_my_proposal')]
#[Description('One of the member\'s own proposals, with its current version. Send that version back to save_proposal or withdraw_proposal.')]
#[IsReadOnly]
class GetMyProposal extends ElancerTool
{
    protected ?string $ability = 'proposals:read';

    public function schema(JsonSchema $schema): array
    {
        return ['proposal_id' => $schema->integer()->min(1)->required()];
    }

    protected function answer(Request $request, User $user): array
    {
        abort_unless(ProposalRules::eligible($user), 403);
        $id = (int) $request->validate(['proposal_id' => ['required', 'integer', 'min:1']])['proposal_id'];
        $proposal = Proposal::query()->with('project')->findOrFail($id);
        abort_unless($user->can('view', $proposal), 404);

        return (new ProposalResource($proposal))->resolve($this->http());
    }
}

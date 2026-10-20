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

/** Plan 033: the API's /me/proposals, the caller's own proposals, latest change first. */
#[Name('list_my_proposals')]
#[Description('The member\'s own proposals (as a freelancer), latest change first: status (draft, submitted, reopened, declined, withdrawn, accepted), version, the project, what the client was sent and any private draft changes not yet submitted.')]
#[IsReadOnly]
class ListMyProposals extends ElancerTool
{
    protected ?string $ability = 'proposals:read';

    public function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->integer()->min(1),
            'per_page' => $schema->integer()->min(1)->max(self::MOST_PER_PAGE)->description('Default 20, at most 50.'),
        ];
    }

    protected function answer(Request $request, User $user): array
    {
        abort_unless(ProposalRules::eligible($user), 403);
        $data = $request->validate(self::paging());
        $proposals = Proposal::query()->where('user_id', $user->id)->with('project')->orderByDesc('updated_at')->orderByDesc('id')
            ->paginate((int) ($data['per_page'] ?? self::PER_PAGE), ['*'], 'page', (int) ($data['page'] ?? 1));

        return $this->page($proposals, array_values($proposals->getCollection()->map(fn (Proposal $proposal) => (new ProposalResource($proposal))->resolve($this->http()))->all()));
    }
}

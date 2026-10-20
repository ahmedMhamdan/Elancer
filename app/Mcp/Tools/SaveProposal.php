<?php

namespace App\Mcp\Tools;

use App\Actions\Proposals\SaveProposal as SaveProposalAction;
use App\Http\Resources\Api\V1\ProposalResource;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

/**
 * Plan 033: the API's PUT /projects/{id}/proposal, through the action the proposal page runs. One
 * difference for agents: a field left out keeps its current value instead of being emptied.
 */
#[Name('save_proposal')]
#[Description('Save the member\'s proposal on a project as a private draft (action "save"), or send it to the client (action "submit"). Ask the member before submitting. Send the version you last read (0 when there is no proposal yet); a stale version is refused. Fields you leave out keep their current value. Submitting needs a price, duration_days, a message of at least 20 characters and one answer per screening question, and a published freelancer profile. Files cannot be attached here.')]
class SaveProposal extends ElancerTool
{
    protected ?string $ability = 'proposals:write';

    protected bool $writes = true;

    /** The fields a draft carries, as the proposal page sends them. */
    private const FIELDS = ['price', 'duration_days', 'message', 'answers', 'samples', 'cases'];

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()->min(1)->required(),
            'action' => $schema->string()->enum(['save', 'submit'])->required()->description('"save" keeps it private; "submit" sends it to the client.'),
            'version' => $schema->integer()->min(0)->required()->description('The proposal\'s version as last read, or 0 for a new one.'),
            'price' => $schema->number()->min(1)->max(1000000)->description('The fixed price in USD, up to two decimals.'),
            'duration_days' => $schema->integer()->min(1)->max(3650)->description('Calendar days from funding to the first complete delivery.'),
            'message' => $schema->string()->max(10000)->description('The cover message.'),
            'answers' => $schema->array()->items($schema->string()->max(3000))->max(3)->description('One answer per screening question, in order.'),
            'samples' => $schema->array()->items($schema->object([
                'label' => $schema->string()->max(80)->required(),
                'url' => $schema->string()->max(2000)->required()->description('An https link.'),
            ]))->max(3)->description('Up to three links to work.'),
            'cases' => $schema->array()->items($schema->integer())->max(3)->description('Ids of the member\'s own portfolio case studies to send.'),
        ];
    }

    protected function answer(Request $request, User $user): array
    {
        $given = $request->all();
        $project = Project::query()->findOrFail((int) $request->validate(['project_id' => ['required', 'integer', 'min:1']])['project_id']);
        $proposal = Proposal::query()->where('project_id', $project->id)->where('user_id', $user->id)->first();
        $current = array_intersect_key($proposal->draft ?? $proposal->content ?? [], array_flip(self::FIELDS));
        $input = ['answers' => [], 'samples' => [], ...$current, ...array_intersect_key($given, array_flip([...self::FIELDS, 'action', 'version']))];

        $result = SaveProposalAction::handle($user, $project, $input);
        if ($result['conflict']) {
            abort(409, __('This proposal changed since the version you sent (you sent :sent, it is now :current). Read it again before saving.', [
                'sent' => (int) $input['version'], 'current' => (int) ($result['proposal']->version ?? 0),
            ]));
        }
        $saved = $result['proposal'];

        return ['created' => $saved->wasRecentlyCreated, ...(new ProposalResource($saved->load('project')))->resolve($this->http())];
    }
}

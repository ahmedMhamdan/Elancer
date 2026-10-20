<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\ClientProjectController;
use App\Models\Contract;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/** Plan 033: My projects (plan 032 part 6) for a client's agent: the same lists, counts and rules, read only. */
#[Name('list_my_projects')]
#[Description('The member\'s own projects as a client, latest change first, with how many proposals each received and how many still wait for a first answer. Lists: all, draft, open (accepting proposals), closed (cutoff passed), hired, waiting (proposals waiting for a reply) and trash. Read only.')]
#[IsReadOnly]
class ListMyProjects extends ElancerTool
{
    protected ?string $ability = 'hiring:read';

    private const LISTS = ['all', 'draft', 'open', 'closed', 'hired', 'waiting', 'trash'];

    public function schema(JsonSchema $schema): array
    {
        return [
            'list' => $schema->string()->enum(self::LISTS)->description('Default all.'),
            'page' => $schema->integer()->min(1),
            'per_page' => $schema->integer()->min(1)->max(self::MOST_PER_PAGE)->description('Default 20, at most 50.'),
        ];
    }

    protected function answer(Request $request, User $user): array
    {
        // Q64: a suspended client still reads their own list, as on the page.
        abort_unless($user->keepsExistingAccess() && $user->onboarding_completed_at !== null, 403);
        $data = $request->validate(['list' => ['nullable', 'in:'.implode(',', self::LISTS)], ...self::paging()]);
        $list = $data['list'] ?? 'all';
        $mine = fn (): Builder => Project::query()->where('user_id', $user->id);
        $projects = ClientProjectController::listed($mine(), $list, $user->id)->with('category:id,categoryname')
            ->withCount(['proposals as applicants_count' => fn ($query) => ClientProjectController::applicants($query, $user->id)])
            ->withCount(['proposals as waiting_count' => fn ($query) => ClientProjectController::waiting($query, $user->id)])
            ->orderByDesc('updated_at')->orderByDesc('id')
            ->paginate((int) ($data['per_page'] ?? self::PER_PAGE), ['*'], 'page', (int) ($data['page'] ?? 1));
        // The hired freelancer as the client met them: the accepted application's frozen profile.
        $contracts = Contract::query()->where('client_id', $user->id)->whereIn('project_id', $projects->getCollection()->where('status', 'hired')->pluck('id'))
            ->orderByDesc('id')->get()->unique('project_id')->keyBy('project_id');
        $hired = Proposal::query()->whereIn('id', $contracts->pluck('proposal_id'))->get()->keyBy('id');

        $items = $projects->getCollection()->map(function (Project $project) use ($contracts, $hired): array {
            $contract = $contracts->get($project->id);

            return [
                'id' => $project->id,
                'title' => $project->title,
                'status' => $project->status,
                'category' => $project->category?->categoryname,
                'budget' => ['min' => $project->budget_min, 'max' => $project->budget_max, 'currency' => 'USD'],
                'published_at' => $project->published_at?->toIso8601String(),
                'application_closes_at' => $project->application_closes_at?->toIso8601String(),
                'updated_at' => $project->getAttribute('updated_at')?->toIso8601String(),
                'in_trash' => $project->deleted_at !== null,
                'hidden_by_moderation' => $project->moderated_at !== null,
                'proposals_received' => (int) $project->getAttribute('applicants_count'),
                'waiting_for_reply' => (int) $project->getAttribute('waiting_count'),
                'hire' => $contract === null ? null : [
                    'contract_id' => $contract->id, 'status' => $contract->status, 'delivery_due_at' => $contract->delivery_due_at?->toIso8601String(),
                    'freelancer' => $hired->get($contract->proposal_id)?->profile_snapshot['name'] ?? null,
                ],
                'url' => $project->status === 'draft' || $project->deleted_at !== null ? route('projects.edit', $project) : route('jobs.show', $project),
            ];
        });

        return [
            'list' => $list,
            'counts' => [
                ...collect(['all', 'draft', 'open', 'closed', 'hired', 'trash'])->mapWithKeys(fn (string $name) => [$name => ClientProjectController::listed($mine(), $name, $user->id)->count()])->all(),
                'waiting' => ClientProjectController::waiting(Proposal::query()->whereIn('project_id', $mine()->where('status', 'published')->select('id')), $user->id)->count(),
            ],
            ...$this->page($projects, array_values($items->all())),
        ];
    }
}

<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\ProjectDiscoveryController;
use App\Http\Resources\Api\V1\ProjectResource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/** Plan 033: the public project search, by the rules, filters and figures of /jobs and the API. */
#[Name('search_projects')]
#[Description('Search the public projects on Elancer (fixed-price, one hire each), newest first, or best match with the member\'s own profile skills. Returns each project\'s title, excerpt, category, skills, budget in USD, application cutoff, whether it is open and how many proposals it has received.')]
#[IsReadOnly]
class SearchProjects extends ElancerTool
{
    protected ?string $ability = 'projects:read';

    public function schema(JsonSchema $schema): array
    {
        return [
            'q' => $schema->string()->max(100)->description('Words to look for in the title and brief.'),
            'category' => $schema->string()->description('A category slug.'),
            'skills' => $schema->array()->items($schema->integer())->max(15)->description('Skill ids from the catalog.'),
            'skill_mode' => $schema->string()->enum(['any', 'all'])->description('Whether a project needs any or all of the skills. Default any.'),
            'budget_min' => $schema->number()->min(1)->description('Lowest budget in USD.'),
            'budget_max' => $schema->number()->min(1)->description('Highest budget in USD.'),
            'posted' => $schema->string()->enum(['any', '1', '7', '30'])->description('Posted within this many days.'),
            'proposals' => $schema->string()->enum(['any', '0-4', '5-9', '10-19', '20'])->description('How many proposals the project has received.'),
            'status' => $schema->string()->enum(['open', 'all'])->description('Open for applications only (default), or closed ones too.'),
            'sort' => $schema->string()->enum(['newest', 'match'])->description('Newest first (default), or the best match with the member\'s profile skills.'),
            'page' => $schema->integer()->min(1),
            'per_page' => $schema->integer()->min(1)->max(self::MOST_PER_PAGE)->description('Default 20, at most 50.'),
        ];
    }

    protected function answer(Request $request, User $user): array
    {
        $data = $request->validate([...ProjectDiscoveryController::rules(), ...self::paging()]);
        abort_if(isset($data['budget_min'], $data['budget_max']) && $data['budget_max'] < $data['budget_min'], 422, __('The maximum budget must be at least the minimum budget.'));
        $skills = $user->profile?->skillTags()->pluck('skills.id')->all() ?? [];
        $results = ProjectDiscoveryController::search(ProjectDiscoveryController::normalise($data), $skills)
            ->paginate((int) ($data['per_page'] ?? self::PER_PAGE), ['*'], 'page', (int) ($data['page'] ?? 1));

        return $this->page($results, array_values($results->getCollection()->map(fn (Project $project) => (new ProjectResource($project))->resolve($this->http()))->all()));
    }
}

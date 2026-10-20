<?php

namespace App\Mcp\Tools;

use App\Http\Resources\Api\V1\ProjectDetailResource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/** Plan 033: one public project as the API's /projects/{id} gives it. */
#[Name('get_project')]
#[Description('One public project in full: the brief, budget, application cutoff, skills, the client\'s screening questions (a submitted proposal answers each), clarifications, and how many proposals and files it has. Drafts, hidden projects and demo projects do not exist here.')]
#[IsReadOnly]
class GetProject extends ElancerTool
{
    protected ?string $ability = 'projects:read';

    public function schema(JsonSchema $schema): array
    {
        return ['project_id' => $schema->integer()->min(1)->required()->description('The project id from search_projects.')];
    }

    protected function answer(Request $request, User $user): array
    {
        $id = (int) $request->validate(['project_id' => ['required', 'integer', 'min:1']])['project_id'];
        $project = Project::query()->visible()->findOrFail($id);
        $project->load(['category', 'skills', 'user.profile']);
        $project->loadCount(['proposals as proposals_received' => fn (Builder $query) => $query->whereNotNull('submitted_at'), 'attachments']);

        return (new ProjectDetailResource($project))->resolve($this->http());
    }
}

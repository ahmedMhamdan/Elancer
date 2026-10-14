<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProjectDiscoveryController;
use App\Http\Resources\Api\V1\ProjectDetailResource;
use App\Http\Resources\Api\V1\ProjectResource;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Plan 028: the public projects, by the same rules, filters and figures as /jobs. */
class ProjectController extends Controller
{
    public const PER_PAGE = 20;

    public const MOST_PER_PAGE = 50;

    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([...ProjectDiscoveryController::rules(), 'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MOST_PER_PAGE]]);
        abort_if(isset($data['budget_min'], $data['budget_max']) && $data['budget_max'] < $data['budget_min'], 422, __('The maximum budget must be at least the minimum budget.'));
        $skills = $request->user()->profile?->skillTags()->pluck('skills.id')->all() ?? [];
        // Newest first unless the caller asks for the best match with their own profile's skills.
        $filters = ProjectDiscoveryController::normalise($data);

        return ProjectResource::collection(ProjectDiscoveryController::search($filters, $skills)->paginate((int) ($data['per_page'] ?? self::PER_PAGE))->withQueryString());
    }

    public function show(Project $project): ProjectDetailResource
    {
        // The public page only: a project that is a draft, hidden by moderation or kept inside the demo does not exist here.
        abort_unless(Project::query()->visible()->whereKey($project->id)->exists(), 404);
        $project->load(['category', 'skills', 'user.profile']);
        $project->loadCount(['proposals as proposals_received' => fn (Builder $query) => $query->whereNotNull('submitted_at'), 'attachments']);

        return new ProjectDetailResource($project);
    }
}

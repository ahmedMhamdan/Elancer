<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Controllers\ProjectDiscoveryController;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Plan 028: a public project as /jobs lists it. Only the fields named here leave, whatever the model has loaded.
 *
 * @mixin Project
 */
class ProjectResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Project $project */
        $project = $this->resource;
        $summary = ProjectDiscoveryController::summary($project);

        return [
            'id' => $summary['id'],
            'title' => $summary['title'],
            'excerpt' => $summary['excerpt'],
            'category' => $project->category === null ? null : ['name' => $project->category->categoryname, 'slug' => $project->category->slug],
            'skills' => $project->skills->pluck('name')->values()->all(),
            'budget' => ['min' => $summary['budget_min'], 'max' => $summary['budget_max'], 'currency' => 'USD'],
            'published_at' => $summary['published_at'],
            'application_closes_at' => $summary['application_closes_at'],
            'open' => (bool) $summary['open'],
            'proposals_received' => $summary['proposals_received'],
            // Q11: how many files the brief carries is public; their names and the files are not.
            'attachments' => (int) $project->getAttribute('attachments_count'),
            'url' => route('jobs.show', $project),
        ];
    }
}

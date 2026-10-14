<?php

namespace App\Http\Resources\Api\V1;

use App\Actions\Contracts\PublicReviews;
use App\Actions\Demo\DemoWorld;
use App\Models\Project;
use App\Models\ProjectClarification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Plan 028: the public project page: the brief, its dated notes and the same limited summary of the client. */
class ProjectDetailResource extends ProjectResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Project $project */
        $project = $this->resource;
        // Plan 027: a note a demo account added after the saved starting point is read inside the demo only.
        $clarifications = $project->clarifications()->when(DemoWorld::hides($project->user_id), fn ($notes) => $notes->where('created_at', '<=', DemoWorld::hiddenSince()))->orderBy('id')->get();

        return [
            ...parent::toArray($request),
            'description' => $project->description,
            'screening_questions' => $project->screening_questions ?? [],
            'clarifications' => $clarifications->map(fn (ProjectClarification $note) => ['body' => $note->body, 'created_at' => $note->created_at->toIso8601String()])->all(),
            // Q18: completed projects and the published rating as a client. Never contact details or spending.
            'client' => [
                'name' => $project->user->profile?->company ?: Str::before($project->user->name, ' '),
                'country' => $project->user->profile?->country,
                'member_since' => $project->user->created_at?->format('Y'),
                ...PublicReviews::record($project->user_id, 'client'),
            ],
        ];
    }
}

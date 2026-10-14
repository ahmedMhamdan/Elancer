<?php

namespace App\Http\Resources\Api\V1;

use App\Actions\Demo\DemoWorld;
use App\Actions\Proposals\ProposalRules;
use App\Models\Proposal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Plan 028: a proposal as its author reads it. The client's private notes and labels are never here.
 *
 * @mixin Proposal
 */
class ProposalResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Proposal $proposal */
        $proposal = $this->resource;

        return [
            'id' => $proposal->id,
            'status' => $proposal->status,
            // Sent back with a change or a withdrawal, so a stale copy is refused instead of overwriting a newer one.
            'version' => $proposal->version,
            'project' => ['id' => $proposal->project_id, 'title' => $proposal->project->title, 'application_closes_at' => $proposal->project->application_closes_at?->toIso8601String(),
                'url' => route('jobs.show', $proposal->project_id)],
            // What the client was sent, and the private changes not yet submitted.
            'content' => $proposal->content,
            'draft' => $proposal->draft,
            'submitted_at' => $proposal->submitted_at?->toIso8601String(),
            'updated_at' => $proposal->getAttribute('updated_at')?->toIso8601String(),
            'editable' => ProposalRules::editable($proposal->project, $proposal),
            // Plan 027: an application to a demo project, which no real client reads or answers.
            'sample' => DemoWorld::trial($proposal->user_id, $proposal->project->user_id),
        ];
    }
}

<?php

namespace App\Actions\Proposals;

use App\Actions\Demo\DemoWorld;
use App\Models\Offer;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\ProposalEvent;
use App\Models\User;

/** Plan 028: what the proposal pages and the API both ask before a freelancer applies, changes or withdraws. */
class ProposalRules
{
    public static function eligible(?User $user): bool
    {
        return $user !== null && $user->canParticipateInMarketplace() && $user->onboarding_completed_at !== null;
    }

    public static function editable(Project $project, ?Proposal $proposal): bool
    {
        // A project hidden by moderation takes no new applications; a proposal already submitted keeps working.
        $open = in_array($proposal?->status, ['submitted', 'reopened'], true) ? Project::query()->listable() : Project::query()->visible();
        if ($project->status !== 'published' || ! $open->whereKey($project->id)->exists()) {
            return false;
        }
        if ($proposal && Offer::query()->where('proposal_id', $proposal->id)->where('status', 'pending')->where('expires_at', '>', now())->exists()) {
            return false;
        }
        if ($proposal?->status === 'declined') {
            return false;
        }

        return in_array($proposal?->status, ['submitted', 'reopened'], true) || (bool) $project->application_closes_at?->isFuture();
    }

    /** Plan 027: a demo account never applies to a member's project; a member may try applying to a demo one. */
    public static function closed(int $applicant, int $owner): bool
    {
        return DemoWorld::apart($applicant, $owner) && ! DemoWorld::trial($applicant, $owner);
    }

    /** @param array<string, mixed>|null $content */
    public static function record(Proposal $proposal, int $actor, string $kind, ?array $content = null): void
    {
        $event = new ProposalEvent;
        $event->forceFill(['proposal_id' => $proposal->id, 'actor_id' => $actor, 'kind' => $kind, 'content' => $content])->save();
    }
}

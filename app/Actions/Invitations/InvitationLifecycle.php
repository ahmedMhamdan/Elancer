<?php

namespace App\Actions\Invitations;

use App\Models\Invitation;
use App\Models\Project;
use App\Models\Proposal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class InvitationLifecycle
{
    public static function blocked(int $first, int $second): bool
    {
        return DB::table('user_blocks')->where(fn ($q) => $q->where('user_id', $first)->where('blocked_user_id', $second))
            ->orWhere(fn ($q) => $q->where('user_id', $second)->where('blocked_user_id', $first))->exists();
    }

    public static function open(Project $project): bool
    {
        return $project->status === 'published' && (bool) $project->application_closes_at?->isFuture()
            && Project::query()->visible()->whereKey($project->id)->exists();
    }

    /**
     * open() as a query, for lists (plan 032 part 14): a pending invitation waits only while its project takes applications;
     * once the project closes, is hidden or deleted, it reads "Applications closed".
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public static function openProjects(Builder $query): Builder
    {
        return $query->visible()->whereNull($query->qualifyColumn('deleted_at'))
            ->where($query->qualifyColumn('status'), 'published')->where($query->qualifyColumn('application_closes_at'), '>', now());
    }

    public static function event(Invitation $invitation, string $kind): void
    {
        DB::table('invitation_events')->insert(['invitation_id' => $invitation->id, 'kind' => $kind, 'created_at' => now()]);
    }

    // Caller holds the applicant/project locks and the proposal submission transaction.
    public static function accept(Proposal $proposal): void
    {
        $invitation = Invitation::query()->where('project_id', $proposal->project_id)->where('recipient_id', $proposal->user_id)->lockForUpdate()->first();
        if ($invitation && in_array($invitation->status, ['pending', 'declined'], true)) {
            $invitation->forceFill(['status' => 'accepted', 'version' => $invitation->version + 1])->save();
            self::event($invitation, 'accepted');
        }
    }
}

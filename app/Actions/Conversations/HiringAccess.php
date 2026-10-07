<?php

namespace App\Actions\Conversations;

use App\Actions\Invitations\InvitationLifecycle;
use App\Models\Contract;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;

class HiringAccess
{
    public static function conversationWritable(Proposal $proposal): bool
    {
        if (Contract::query()->where('proposal_id', $proposal->id)->exists()) {
            // Blocking and suspension cannot remove contractual communication.
            return User::query()->whereIn('id', [$proposal->user_id, $proposal->project->user_id])
                ->whereIn('status', ['active', 'suspended'])->whereNotNull('email_verified_at')->count() === 2;
        }

        return self::writable($proposal);
    }

    public static function writable(Proposal $proposal): bool
    {
        return $proposal->submitted_at !== null && in_array($proposal->status, ['submitted', 'reopened'], true)
            // Listable, not visible: a project hidden by moderation takes no new applicants but keeps the hiring already under way.
            && $proposal->project->status === 'published' && Project::query()->listable()->whereKey($proposal->project_id)->exists()
            && User::query()->whereKey($proposal->user_id)->where('status', 'active')->whereNotNull('email_verified_at')->whereNotNull('onboarding_completed_at')->exists()
            && User::query()->whereKey($proposal->project->user_id)->whereNotNull('onboarding_completed_at')->exists()
            && ! InvitationLifecycle::blocked($proposal->project->user_id, $proposal->user_id);
    }
}

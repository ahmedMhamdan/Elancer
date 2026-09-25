<?php

namespace App\Actions\Conversations;

use App\Actions\Invitations\InvitationLifecycle;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;

class HiringAccess
{
    public static function writable(Proposal $proposal): bool
    {
        return $proposal->submitted_at !== null && in_array($proposal->status, ['submitted', 'reopened'], true)
            && $proposal->project->status === 'published' && Project::query()->visible()->whereKey($proposal->project_id)->exists()
            && User::query()->whereKey($proposal->user_id)->where('status', 'active')->whereNotNull('email_verified_at')->whereNotNull('onboarding_completed_at')->exists()
            && User::query()->whereKey($proposal->project->user_id)->whereNotNull('onboarding_completed_at')->exists()
            && ! InvitationLifecycle::blocked($proposal->project->user_id, $proposal->user_id);
    }
}

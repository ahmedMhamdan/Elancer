<?php

namespace App\Actions\Dashboard;

use App\Actions\Payments\ContractFunding;
use App\Enums\WorkspaceRole;
use App\Models\Contract;
use App\Models\ConversationMessage;
use App\Models\Invitation;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class BuildOverview
{
    /** @return array<string, mixed> */
    public function handle(User $user): array
    {
        $client = $user->workspace_role === WorkspaceRole::Client;
        $projects = Project::query()->where('user_id', $user->id);
        // Draft applications are visible only to their author, never to a project owner.
        $proposals = Proposal::query()->whereHas('project')->when($client,
            fn ($query) => $query->whereHas('project', fn ($project) => $project->where('user_id', $user->id))->whereNotNull('submitted_at'),
            fn ($query) => $query->where('user_id', $user->id));
        $contracts = Contract::query()->where(fn ($query) => $query->where('client_id', $user->id)->orWhere('freelancer_id', $user->id));
        $messages = ConversationMessage::query()->whereHas('conversation', fn ($query) => $query->where(fn ($query) => $query->where('client_id', $user->id)->orWhere('freelancer_id', $user->id)));
        $unread = (clone $messages)->where('sender_id', '!=', $user->id)->whereHas('conversation', fn ($query) => $query
            ->where(fn ($query) => $query->where('client_id', $user->id)->where('client_archived', false)->whereColumn('conversation_messages.id', '>', 'conversations.client_read_through'))
            ->orWhere(fn ($query) => $query->where('freelancer_id', $user->id)->where('freelancer_archived', false)->whereColumn('conversation_messages.id', '>', 'conversations.freelancer_read_through')))->count();

        $start = CarbonImmutable::now()->startOfWeek()->subWeeks(7);
        $proposalWeeks = $this->weeks((clone $proposals)->whereNotNull('submitted_at'), 'submitted_at', $start);
        $contractWeeks = $this->weeks(clone $contracts, 'created_at', $start);
        $chart = [];
        for ($week = 0; $week < 8; $week++) {
            $date = $start->addWeeks($week)->toDateString();
            $chart[] = ['week' => $date, 'proposals' => $proposalWeeks[$date] ?? 0, 'contracts' => $contractWeeks[$date] ?? 0];
        }

        $work = $client
            ? (clone $projects)->withCount(['proposals' => fn ($query) => $query->whereNotNull('submitted_at')])->latest('updated_at')->latest('id')->limit(4)->get()->map(fn (Project $project) => [
                'id' => 'project-'.$project->id, 'title' => $project->title, 'status' => $project->status, 'kind' => 'project',
                'href' => '/my-projects/'.$project->id.'/edit', 'count' => $project->getAttribute('proposals_count'),
                'updated_at' => $project->getAttribute('updated_at')->toISOString(),
            ])->all()
            : (clone $proposals)->with('project')->latest('updated_at')->latest('id')->limit(4)->get()->map(fn (Proposal $proposal) => [
                'id' => 'proposal-'.$proposal->id, 'title' => $proposal->project->title, 'status' => $proposal->status, 'kind' => 'proposal',
                'href' => $proposal->status === 'draft' ? '/jobs/'.$proposal->project_id.'/apply' : '/proposals/'.$proposal->id,
                'count' => null, 'updated_at' => $proposal->getAttribute('updated_at')->toISOString(),
            ])->all();
        $agreements = (clone $contracts)->latest('id')->limit(3)->get()->map(fn (Contract $contract) => [
            'id' => 'contract-'.$contract->id, 'title' => $contract->agreement['project_title'] ?? null, 'status' => $contract->status,
            'kind' => 'contract', 'href' => '/contracts/'.$contract->id, 'count' => null,
            'updated_at' => $contract->getAttribute('created_at')->toISOString(),
        ])->all();

        $activity = (clone $messages)->with(['conversation.client', 'conversation.freelancer', 'conversation.proposal.project'])
            ->latest('created_at')->latest('id')->limit(6)->get()->map(fn (ConversationMessage $message) => [
                'id' => 'message-'.$message->id, 'kind' => 'message', 'actor' => $message->sender_id === $user->id ? null : ($message->sender_id === $message->conversation->client_id ? $message->conversation->client->name : $message->conversation->freelancer->name),
                'title' => $message->conversation->proposal->project->title, // Q68: a message hidden by moderation gives no preview.
                'preview' => $message->moderated_at === null ? Str::limit($message->body, 140) : null,
                'href' => '/messages/'.$message->conversation_id, 'created_at' => $message->created_at->toISOString(),
            ])->all();

        foreach ((clone $proposals)->with(['project.user'])->whereNotNull('submitted_at')->latest('submitted_at')->limit(4)->get() as $proposal) {
            $activity[] = ['id' => 'proposal-'.$proposal->id, 'kind' => 'proposal',
                'actor' => $client ? User::query()->whereKey($proposal->user_id)->value('name') : null,
                'title' => $proposal->project->title, 'preview' => null, 'href' => '/proposals/'.$proposal->id,
                'created_at' => $proposal->submitted_at->toISOString()];
        }
        foreach ((clone $contracts)->latest('id')->limit(4)->get() as $contract) {
            $activity[] = ['id' => 'contract-'.$contract->id, 'kind' => 'contract', 'actor' => $contract->freelancer_id === $user->id ? null : User::query()->whereKey($contract->freelancer_id)->value('name'),
                'title' => $contract->agreement['project_title'] ?? null, 'preview' => null, 'href' => '/contracts/'.$contract->id,
                'created_at' => $contract->getAttribute('created_at')->toISOString()];
        }
        usort($activity, fn ($left, $right) => strcmp($right['created_at'], $left['created_at']));
        $activity = array_slice($activity, 0, 6);

        $profile = $user->profile;
        $checks = $client
            ? ['bio' => trim($profile->bio ?? '') !== '', 'location' => trim($profile->location ?? '') !== '']
            : ['headline' => trim($profile->headline ?? '') !== '', 'bio' => trim($profile->bio ?? '') !== '', 'skills' => $profile?->skillTags()->exists() ?? false];

        return [
            'client' => $client,
            'counts' => ['projects' => (clone $projects)->count(), 'proposals' => (clone $proposals)->count(),
                'invitations' => Invitation::query()->where('recipient_id', $user->id)->where('status', 'pending')->count(),
                'contracts' => (clone $contracts)->count(), 'awaiting_payment' => (clone $contracts)->where('status', 'awaiting_payment')->count(), 'unread' => $unread],
            'finance' => ContractFunding::summary($user->id)[$client ? 'client' : 'freelancer'],
            'chart' => $chart, 'work' => $work, 'agreements' => $agreements, 'activity' => $activity,
            'profile_checks' => $checks,
        ];
    }

    /**
     * Calendar buckets are computed in PHP so the query is portable across SQLite, MySQL and PostgreSQL.
     *
     * @param  Builder<Proposal>|Builder<Contract>  $query
     * @return array<string, int>
     */
    private function weeks(Builder $query, string $column, CarbonImmutable $start): array
    {
        $counts = [];
        foreach ($query->whereBetween($column, [$start, now()])->pluck($column) as $date) {
            $week = CarbonImmutable::parse($date)->startOfWeek()->toDateString();
            $counts[$week] = ($counts[$week] ?? 0) + 1;
        }

        return $counts;
    }
}

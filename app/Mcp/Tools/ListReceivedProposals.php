<?php

namespace App\Mcp\Tools;

use App\Actions\Demo\DemoWorld;
use App\Actions\Proposals\ProposalRules;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/** Plan 033: the applicants page (ProposalController::applicants) for a client's agent, read only. */
#[Name('list_received_proposals')]
#[Description('The submitted proposals one of the member\'s own projects received: the freelancer as they applied (name, headline, country), price, duration, status, the client\'s own label (received, shortlisted, archived) and the start of the message. Read only: nothing is marked as seen and the freelancer is not told.')]
#[IsReadOnly]
class ListReceivedProposals extends ElancerTool
{
    protected ?string $ability = 'hiring:read';

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()->min(1)->required()->description('One of the member\'s projects, from list_my_projects.'),
            'organization' => $schema->string()->enum(['received', 'shortlisted', 'archived'])->description('Only proposals with this label.'),
            'sort' => $schema->string()->enum(['newest', 'price', 'duration'])->description('Newest first (default), lowest price or shortest duration first.'),
            'page' => $schema->integer()->min(1),
            'per_page' => $schema->integer()->min(1)->max(self::MOST_PER_PAGE)->description('Default 20, at most 50.'),
        ];
    }

    protected function answer(Request $request, User $user): array
    {
        abort_unless(ProposalRules::eligible($user), 403);
        $data = $request->validate(['project_id' => ['required', 'integer', 'min:1'], 'organization' => ['nullable', 'in:received,shortlisted,archived'],
            'sort' => ['nullable', 'in:newest,price,duration'], ...self::paging()]);
        // Another member's project does not exist for this caller.
        $project = Project::query()->where('user_id', $user->id)->findOrFail((int) $data['project_id']);
        $query = $project->proposals()->whereNotNull('submitted_at');
        DemoWorld::ownApplicants($query, $project->user_id);
        if (! empty($data['organization'])) {
            $query->where('organization', $data['organization']);
        }
        match ($data['sort'] ?? 'newest') {
            'price' => $query->orderBy('price'),
            'duration' => $query->orderBy('duration_days'),
            default => $query->orderByDesc('submitted_at'),
        };
        $proposals = $query->orderByDesc('id')->paginate((int) ($data['per_page'] ?? self::PER_PAGE), ['*'], 'page', (int) ($data['page'] ?? 1));

        return [
            'project' => ['id' => $project->id, 'title' => $project->title, 'status' => $project->status],
            ...$this->page($proposals, array_values($proposals->getCollection()->map(fn (Proposal $proposal): array => [
                'id' => $proposal->id,
                'status' => $proposal->status,
                'organization' => $proposal->organization,
                'submitted_at' => $proposal->submitted_at?->toIso8601String(),
                'price' => $proposal->content['price'] ?? null,
                'duration_days' => $proposal->content['duration_days'] ?? null,
                'message_start' => Str::limit((string) ($proposal->content['message'] ?? ''), 280),
                'freelancer' => self::freelancer($proposal),
                'url' => route('proposals.show', $proposal),
            ])->all())),
        ];
    }

    /**
     * The freelancer as they were when they applied, as the client's pages show them.
     *
     * @return array<string, mixed>
     */
    public static function freelancer(Proposal $proposal): array
    {
        $snapshot = $proposal->profile_snapshot ?? [];

        return ['name' => $snapshot['name'] ?? null, 'headline' => $snapshot['headline'] ?? null, 'country' => $snapshot['country'] ?? null];
    }
}

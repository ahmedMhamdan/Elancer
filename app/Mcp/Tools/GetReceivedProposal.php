<?php

namespace App\Mcp\Tools;

use App\Actions\Proposals\ProposalCases;
use App\Actions\Proposals\ProposalRules;
use App\Http\Controllers\ProposalController;
use App\Models\Contract;
use App\Models\Conversation;
use App\Models\Offer;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/** Plan 033: one received proposal as its client reads it on the proposal page (ProposalController::show). */
#[Name('get_received_proposal')]
#[Description('One proposal a project of the member\'s received, in full as the client reads it: price, duration, message, the answers to the screening questions, links, file names, the case studies sent with it, the freelancer as they applied, the client\'s own label and private note, and whether a conversation or offers exist. Read only: nothing is marked as seen and the freelancer is not told.')]
#[IsReadOnly]
class GetReceivedProposal extends ElancerTool
{
    protected ?string $ability = 'hiring:read';

    public function schema(JsonSchema $schema): array
    {
        return ['proposal_id' => $schema->integer()->min(1)->required()->description('A proposal id from list_received_proposals.')];
    }

    protected function answer(Request $request, User $user): array
    {
        $id = (int) $request->validate(['proposal_id' => ['required', 'integer', 'min:1']])['proposal_id'];
        $proposal = Proposal::query()->with('project')->findOrFail($id);
        // Q64: a suspended member still reads the proposal behind an existing contract, as on the page.
        if (! ($user->keepsExistingAccess() && Contract::query()->where('proposal_id', $proposal->id)->exists())) {
            abort_unless(ProposalRules::eligible($user), 403);
        }
        abort_unless(ProposalController::clientReads($user->id, $proposal), 404);
        $content = $proposal->content ?? [];
        $questions = $proposal->project->screening_questions ?? [];

        return [
            'id' => $proposal->id,
            'project' => ['id' => $proposal->project_id, 'title' => $proposal->project->title],
            'status' => $proposal->status,
            'organization' => $proposal->organization,
            'client_note' => $proposal->client_note,
            'version' => $proposal->version,
            'submitted_at' => $proposal->submitted_at?->toIso8601String(),
            'price' => $content['price'] ?? null,
            'duration_days' => $content['duration_days'] ?? null,
            'message' => $content['message'] ?? null,
            'answers' => self::answers(array_values((array) ($content['answers'] ?? [])), $questions),
            'samples' => array_values($content['samples'] ?? []),
            'files' => array_values(array_map(fn (array $file) => ['name' => $file['name'] ?? null, 'size' => $file['size'] ?? null], $content['files'] ?? [])),
            'case_studies' => array_map(fn (array $case) => ($case['withheld'] ?? false) ? ['withheld' => true] : ['title' => $case['title'], 'summary' => $case['summary'], 'body' => $case['body'], 'skills' => $case['skills'], 'links' => $case['links'], 'elancer_work' => $case['elancer_work']],
                ProposalCases::resolve($proposal, $proposal->content, false)),
            'freelancer' => [...ListReceivedProposals::freelancer($proposal), 'bio' => $proposal->profile_snapshot['bio'] ?? null,
                'skills' => array_column($proposal->profile_snapshot['skills'] ?? [], 'name'), 'member_since' => $proposal->profile_snapshot['member_since'] ?? null],
            'conversation' => Conversation::query()->where('proposal_id', $proposal->id)->exists(),
            'offers' => Offer::query()->where('proposal_id', $proposal->id)->orderByDesc('id')->get(['id', 'status'])->map(fn (Offer $offer) => ['id' => $offer->id, 'status' => $offer->status])->all(),
            'url' => route('proposals.show', $proposal),
        ];
    }

    /**
     * Each answer beside the screening question it answers, in the brief's order.
     *
     * @param  list<mixed>  $answers
     * @param  array<int, mixed>  $questions
     * @return list<array{question: mixed, answer: mixed}>
     */
    private static function answers(array $answers, array $questions): array
    {
        $paired = [];
        foreach ($answers as $index => $answer) {
            $paired[] = ['question' => $questions[$index] ?? null, 'answer' => $answer];
        }

        return $paired;
    }
}

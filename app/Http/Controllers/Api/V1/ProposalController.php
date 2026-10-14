<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Proposals\ProposalRules;
use App\Actions\Proposals\SaveProposal;
use App\Actions\Proposals\WithdrawProposal;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProposalResource;
use App\Models\Project;
use App\Models\Proposal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Plan 028: the caller's own proposals. Saving and withdrawing run the actions the proposal pages run. */
class ProposalController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless(ProposalRules::eligible($request->user()), 403);
        $data = $request->validate(['page' => ['nullable', 'integer', 'min:1', 'max:100000'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:'.ProjectController::MOST_PER_PAGE]]);

        return ProposalResource::collection(Proposal::query()->where('user_id', $request->user()->id)->with('project')->orderByDesc('updated_at')->orderByDesc('id')
            ->paginate((int) ($data['per_page'] ?? ProjectController::PER_PAGE))->withQueryString());
    }

    public function show(Request $request, Proposal $proposal): ProposalResource
    {
        abort_unless(ProposalRules::eligible($request->user()), 403);
        // Someone else's proposal does not exist for this caller.
        abort_unless($request->user()->can('view', $proposal), 404);

        return new ProposalResource($proposal);
    }

    public function save(Request $request, Project $project): JsonResponse
    {
        $result = SaveProposal::handle($request->user(), $project, $request->all());
        $proposal = $result['proposal'];
        if ($result['conflict']) {
            return response()->json(['message' => __('This proposal changed since the version you sent. Read it again before saving.'),
                'data' => $proposal ? (new ProposalResource($proposal))->resolve($request) : null], 409);
        }

        return (new ProposalResource($proposal))->response($request)->setStatusCode($proposal->wasRecentlyCreated ? 201 : 200);
    }

    public function withdraw(Request $request, Proposal $proposal): ProposalResource
    {
        abort_unless($request->user()->can('view', $proposal), 404);
        WithdrawProposal::handle($request->user(), $proposal, $request->all());

        return new ProposalResource($proposal->fresh());
    }
}

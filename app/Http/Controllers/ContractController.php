<?php

namespace App\Http\Controllers;

use App\Actions\Contracts\ContractWork;
use App\Actions\Payments\ContractFunding;
use App\Models\Contract;
use App\Models\ContractAmendment;
use App\Models\ContractCancellation;
use App\Models\ContractReview;
use App\Models\ContractSubmission;
use App\Models\ContractSubmissionFile;
use App\Models\PaymentAttempt;
use App\Payments\Gateways;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ContractController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user()->id;

        return Inertia::render('contracts/index', ['contracts' => Contract::query()
            ->where(fn ($q) => $q->where('client_id', $user)->orWhere('freelancer_id', $user))
            ->orderByDesc('id')->paginate(20)->through(fn (Contract $contract) => $this->details($contract, $user))]);
    }

    public function show(Request $request, Contract $contract, Gateways $gateways): Response
    {
        $user = $request->user()->id;
        abort_unless($contract->contains($user), 404);
        $submissions = $contract->submissions()->with(['files', 'revision'])->get();
        $cancellations = ContractCancellation::query()->where('contract_id', $contract->id)->orderBy('id')->get();
        $cancellation = $cancellations->last();
        $amendments = ContractAmendment::query()->where('contract_id', $contract->id)->orderBy('id')->get();

        return Inertia::render('contracts/show', [
            'contract' => $this->details($contract, $user),
            'payment' => PaymentAttempt::query()->where('contract_id', $contract->id)->latest('id')->first()?->summary(),
            'providers' => $contract->client_id === $user ? $gateways->available() : [],
            'funding_paused' => ContractFunding::paused($contract),
            'submissions' => $submissions->map(fn (ContractSubmission $submission) => [
                ...$submission->only(['id', 'number', 'message', 'links', 'created_at']),
                'files' => $submission->files->map(fn (ContractSubmissionFile $file) => $file->only(['id', 'name', 'size'])),
                'revision' => $submission->revision?->only(['round', 'changes', 'created_at']),
            ])->reverse()->values(),
            'cancellation' => $cancellation ? [...$cancellation->only(['status', 'reason', 'prior_status', 'refund_status', 'refund_failure', 'created_at']), 'mine' => $cancellation->requester_id === $user] : null,
            'amendments' => $amendments->map(fn (ContractAmendment $amendment) => [
                ...$amendment->only(['id', 'status', 'reason', 'date_kind', 'new_due_at', 'previous_due_at', 'extra_rounds', 'created_at', 'decided_at']),
                'mine' => $amendment->proposer_id === $user,
            ])->reverse()->values(),
            'activity' => $this->activity($contract, $submissions, $cancellations, $amendments),
            'reviews' => $this->reviews($contract, $user),
            'portfolio' => PortfolioController::forContract($contract, $user),
        ]);
    }

    /** @return array<string, mixed> */
    private function details(Contract $contract, int $user): array
    {
        return [...$contract->only(['id', 'project_id', 'offer_id', 'conversation_id', 'status', 'agreement', 'created_at', 'funded_at', 'delivery_due_at', 'revision_due_at', 'completed_at', 'cancelled_at', 'revisions_used']),
            'revision_rounds' => $contract->revisionRounds(),
            // Informational only: a late delivery changes nothing by itself.
            'overdue' => $contract->status === 'active' && $contract->delivery_due_at?->isPast() === true,
            'revision_overdue' => $contract->status === 'revision_requested' && $contract->revision_due_at?->isPast() === true,
            'is_client' => $contract->client_id === $user];
    }

    /**
     * Derived from the stored records, newest first.
     *
     * @param  Collection<int, ContractSubmission>  $submissions
     * @param  Collection<int, ContractCancellation>  $cancellations
     * @param  Collection<int, ContractAmendment>  $amendments
     * @return list<array{kind: string, at: mixed, number: int|null}>
     */
    private function activity(Contract $contract, Collection $submissions, Collection $cancellations, Collection $amendments): array
    {
        $events = [['kind' => 'accepted', 'at' => $contract->agreement['accepted_at'] ?? $contract->getAttribute('created_at'), 'number' => null]];
        if ($contract->funded_at) {
            $events[] = ['kind' => 'funded', 'at' => $contract->funded_at, 'number' => null];
        }
        foreach ($submissions as $submission) {
            $events[] = ['kind' => 'delivered', 'at' => $submission->created_at, 'number' => $submission->number];
            if ($submission->revision) {
                $events[] = ['kind' => 'revision', 'at' => $submission->revision->created_at, 'number' => $submission->revision->round];
            }
        }
        if ($contract->completed_at) {
            $events[] = ['kind' => 'completed', 'at' => $contract->completed_at, 'number' => null];
        }
        foreach ($cancellations as $cancellation) {
            if ($cancellation->prior_status !== 'awaiting_payment') {
                $events[] = ['kind' => 'cancellation_requested', 'at' => $cancellation->created_at, 'number' => null];
            }
            if ($cancellation->decided_at && in_array($cancellation->status, ['declined', 'withdrawn', 'accepted', 'refunded'], true)) {
                $events[] = ['kind' => 'cancellation_'.($cancellation->status === 'refunded' ? 'accepted' : $cancellation->status), 'at' => $cancellation->decided_at, 'number' => null];
            }
            if ($cancellation->refunded_at) {
                $events[] = ['kind' => 'refunded', 'at' => $cancellation->refunded_at, 'number' => null];
            }
        }
        foreach ($amendments as $amendment) {
            $events[] = ['kind' => 'amendment_proposed', 'at' => $amendment->created_at, 'number' => null];
            if ($amendment->decided_at && in_array($amendment->status, ['accepted', 'declined', 'withdrawn'], true)) {
                $events[] = ['kind' => 'amendment_'.$amendment->status, 'at' => $amendment->decided_at, 'number' => null];
            }
        }
        if ($contract->cancelled_at) {
            $events[] = ['kind' => 'cancelled', 'at' => $contract->cancelled_at, 'number' => null];
        }
        // Stable, so steps recorded in the same second keep the order they were added in.
        usort($events, fn (array $left, array $right) => Carbon::parse($left['at']) <=> Carbon::parse($right['at']));

        return array_reverse($events);
    }

    /**
     * The counterpart's review stays hidden until publication.
     *
     * @return array<string, mixed>|null
     */
    private function reviews(Contract $contract, int $user): ?array
    {
        if ($contract->completed_at === null) {
            return null;
        }
        $published = ContractWork::reviewsPublished($contract);
        $reviews = ContractReview::query()->where('contract_id', $contract->id)->get()->keyBy('author_id');
        $theirs = $reviews->first(fn (ContractReview $review) => $review->author_id !== $user);

        return ['published' => $published, 'publishes_at' => $contract->completed_at->addDays(ContractWork::REVIEW_DAYS),
            'mine' => $reviews->get($user)?->only(['rating', 'body']),
            'theirs' => $published ? $theirs?->only(['rating', 'body']) : null,
            'theirs_submitted' => $theirs !== null];
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\Contracts\ContractWork;
use App\Actions\Conversations\ConversationThread;
use App\Actions\Payments\ContractFunding;
use App\Models\Contract;
use App\Models\ContractAmendment;
use App\Models\ContractCancellation;
use App\Models\ContractReview;
use App\Models\ContractSubmission;
use App\Models\ContractSubmissionFile;
use App\Models\Conversation;
use App\Models\PaymentAttempt;
use App\Payments\Gateways;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ContractController extends Controller
{
    /** The contract lists by whose move it is; with Completed and Cancelled they add up to All for either side. */
    private const LISTS = ['yours', 'theirs', 'completed', 'cancelled'];

    /**
     * Plan 032 part 8 (Ahmed's pick "B"): one card per contract with where it stands and the next step. Running
     * contracts come first. The lists, counts and total are closures, so switching lists reloads only the cards.
     */
    public function index(Request $request): Response
    {
        $user = $request->user()->id;
        $query = $request->query('list');
        $list = is_string($query) && in_array($query, self::LISTS, true) ? $query : 'all';

        return Inertia::render('contracts/index', [
            'contracts' => fn () => $this->listed($this->mine($user), $list, $user)
                ->with(['proposal:id,profile_snapshot', 'clientProfile:id,user_id,company', 'openCancellation:id,open_contract_id,requester_id,status,prior_status,created_at'])
                ->withCount('submissions')->withMax('submissions as last_delivery_at', 'created_at')
                ->withMax('revisionRequests as revision_asked_at', 'created_at')
                ->withCasts(['last_delivery_at' => 'immutable_datetime', 'revision_asked_at' => 'immutable_datetime'])
                ->orderByRaw("case when status in ('completed', 'cancelled') then 1 else 0 end")->orderByDesc('id')
                ->paginate(20)->withQueryString()->through(fn (Contract $contract) => $this->row($contract, $user)),
            'list' => $list,
            'counts' => fn () => collect(['all', ...self::LISTS])->mapWithKeys(fn (string $name) => [$name => $this->listed($this->mine($user), $name, $user)->count()])->all(),
            // Agreed amounts only, as on Finance: no balance, earnings or payout.
            'agreed' => fn () => number_format((float) $this->mine($user)->where('status', '!=', 'cancelled')->pluck('agreement->amount as amount')->sum(), 2, '.', ''),
        ]);
    }

    /** @return Builder<Contract> */
    private function mine(int $user): Builder
    {
        return Contract::query()->where(fn (Builder $query) => $query->where('client_id', $user)->orWhere('freelancer_id', $user));
    }

    /**
     * One list. The client funds and reviews; the freelancer delivers and revises; an open cancellation request waits
     * on the other side. Every other running contract waits on the other side.
     *
     * @param  Builder<Contract>  $query
     * @return Builder<Contract>
     */
    private function listed(Builder $query, string $list, int $user): Builder
    {
        $yours = fn (Builder $query): Builder => $query->where(fn (Builder $query) => $query
            ->where(fn (Builder $query) => $query->where('client_id', $user)->whereIn('status', ['awaiting_payment', 'submitted']))
            ->orWhere(fn (Builder $query) => $query->where('freelancer_id', $user)->whereIn('status', ['active', 'revision_requested']))
            ->orWhere(fn (Builder $query) => $query->where('status', 'cancellation_pending')
                ->whereHas('openCancellation', fn (Builder $request) => $request->where('status', 'pending')->where('requester_id', '!=', $user))));

        return match ($list) {
            'yours' => $yours($query),
            'theirs' => $query->whereNotIn('status', ['completed', 'cancelled'])->whereNot(fn (Builder $query) => $yours($query)),
            'completed' => $query->where('status', 'completed'),
            'cancelled' => $query->where('status', 'cancelled'),
            default => $query,
        };
    }

    /** Whose move it is, as in listed(): the viewer's, the other side's, or nobody's once the contract is closed. */
    private function turn(Contract $contract, int $user): ?string
    {
        $client = $contract->client_id === $user;
        $request = $contract->openCancellation;

        return match ($contract->status) {
            'awaiting_payment', 'submitted' => $client ? 'yours' : 'theirs',
            'active', 'revision_requested' => $client ? 'theirs' : 'yours',
            'cancellation_pending' => $request?->status === 'pending' && $request->requester_id !== $user ? 'yours' : 'theirs',
            default => null,
        };
    }

    /**
     * One card. The other side as the viewer met them: a client sees the freelancer's proposal profile, a freelancer
     * the client's name and company (client photos are private).
     *
     * @return array<string, mixed>
     */
    private function row(Contract $contract, int $user): array
    {
        $client = $contract->client_id === $user;
        $snapshot = $contract->proposal->profile_snapshot ?? [];
        $request = $contract->openCancellation;

        return [...$contract->only(['id', 'project_id', 'conversation_id', 'status', 'created_at', 'funded_at', 'delivery_due_at', 'revision_due_at', 'completed_at', 'cancelled_at', 'revisions_used']),
            'title' => $contract->agreement['project_title'] ?? '', 'amount' => $contract->agreement['amount'],
            'duration_days' => $contract->agreement['duration_days'], 'revision_rounds' => $contract->revisionRounds(), 'is_client' => $client,
            'counterpart' => $client
                ? ['name' => $snapshot['name'] ?? $contract->agreement['freelancer_name'] ?? null, 'avatar' => $snapshot['avatar'] ?? null, 'company' => null]
                : ['name' => $contract->agreement['client_name'] ?? null, 'avatar' => null, 'company' => $contract->clientProfile?->company],
            'deliveries' => $contract->getAttribute('submissions_count'), 'last_delivery_at' => $contract->getAttribute('last_delivery_at'),
            'revision_asked_at' => $contract->getAttribute('revision_asked_at'),
            'cancellation' => $request ? ['status' => $request->status, 'prior_status' => $request->prior_status, 'created_at' => $request->created_at] : null,
            'overdue' => $contract->status === 'active' && $contract->delivery_due_at?->isPast() === true,
            'turn' => $this->turn($contract, $user)];
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
            'thread' => $this->thread($contract, $user),
        ]);
    }

    /**
     * Plan 031 (Q31): the contract's conversation in the workroom's Messages tab, read as on the Messages page.
     *
     * @return array<string, mixed>|null
     */
    private function thread(Contract $contract, int $user): ?array
    {
        $conversation = Conversation::query()->find($contract->conversation_id);
        if (! $conversation) {
            return null;
        }

        return ['id' => $conversation->id, 'counterpart' => $contract->client_id === $user ? $contract->agreement['freelancer_name'] ?? '' : $contract->agreement['client_name'] ?? '',
            'unread' => ConversationThread::unread($conversation, $user),
            ...ConversationThread::for($conversation, $user, 'messages_page', ['tab' => 'messages'])];
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

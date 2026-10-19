<?php

namespace App\Http\Controllers;

use App\Actions\Conversations\HiringAccess;
use App\Actions\Offers\OfferLifecycle;
use App\Models\Contract;
use App\Models\Conversation;
use App\Models\Offer;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use App\Notifications\WorkspaceEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OfferController extends Controller
{
    /** The lists of the offers inbox, by whose move it is; with Accepted and Closed they add up to All. */
    private const LISTS = ['yours', 'theirs', 'accepted', 'closed'];

    public function index(Request $request): Response
    {
        return $this->inbox($request, null);
    }

    public function create(Request $request, Proposal $proposal): Response
    {
        abort_unless($proposal->project->user_id === $request->user()->id && $proposal->submitted_at, 404);
        abort_unless(HiringAccess::writable($proposal), 403);

        return Inertia::render('offers/create', ['proposal' => $proposal->only(['id', 'version', 'content']), 'project' => $proposal->project->only(['id', 'title'])]);
    }

    public function store(Request $request, Proposal $proposal): RedirectResponse
    {
        abort_unless($proposal->project->user_id === $request->user()->id && $proposal->submitted_at, 404);
        $data = $request->validate([
            'scope' => ['required', 'string', 'min:50', 'max:20000'],
            'deliverables' => ['required', 'array', 'list', 'min:1', 'max:20'],
            'deliverables.*' => ['required', 'string', 'max:200', 'distinct'],
            'amount' => ['required', 'numeric', 'min:1', 'max:1000000', 'decimal:0,2'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:365'],
            'revision_rounds' => ['required', 'integer', 'min:0', 'max:20'],
            'proposal_version' => ['required', 'integer', 'min:1'], 'client_token' => ['required', 'uuid'],
        ]);
        $terms = ['scope' => $data['scope'], 'deliverables' => $data['deliverables'], 'amount' => number_format((float) $data['amount'], 2, '.', ''),
            'currency' => 'USD', 'duration_days' => (int) $data['duration_days'], 'revision_rounds' => (int) $data['revision_rounds']];
        $offer = DB::transaction(function () use ($proposal, $data, $terms): Offer {
            OfferLifecycle::lockParticipants($proposal->project->user_id, $proposal->user_id);
            Project::query()->lockForUpdate()->findOrFail($proposal->project_id);
            $locked = Proposal::query()->lockForUpdate()->findOrFail($proposal->id);
            $existing = Offer::query()->where('client_id', $proposal->project->user_id)->where('client_token', $data['client_token'])->first();
            if ($existing) {
                if ($existing->proposal_id !== $proposal->id || collect($existing->terms)->sortKeys()->all() !== collect($terms)->sortKeys()->all()) {
                    throw ValidationException::withMessages(['offer' => __('This offer request was already used. Reload before trying again.')]);
                }

                return $existing;
            }
            OfferLifecycle::expire($locked->project_id);
            if (! HiringAccess::writable($locked) || $locked->version !== (int) $data['proposal_version']) {
                throw ValidationException::withMessages(['offer' => __('Hiring changed. Reload the proposal before sending an offer.')]);
            }
            if (Offer::query()->where('pending_project_id', $locked->project_id)->exists()) {
                throw ValidationException::withMessages(['offer' => __('This project already has a pending offer. Withdraw it or wait for its expiry.')]);
            }
            $offer = new Offer;
            $offer->forceFill(['project_id' => $locked->project_id, 'pending_project_id' => $locked->project_id, 'proposal_id' => $locked->id,
                'client_id' => $locked->project->user_id, 'freelancer_id' => $locked->user_id, 'terms' => $terms,
                'client_token' => $data['client_token'], 'expires_at' => now()->addHours(72)])->save();
            $this->notify($offer, 'offer_received', $offer->freelancer_id, $offer->client_id, '/offers/'.$offer->id);

            return $offer;
        }, 3);

        return to_route('offers.show', $offer);
    }

    public function show(Request $request, Offer $offer): Response
    {
        abort_unless($offer->contains($request->user()->id), 404);

        return $this->inbox($request, $offer);
    }

    /**
     * Plan 032 part 7: /offers and /offers/{id} are one inbox, the list on one side and the chosen offer on the other.
     * Without a chosen offer the first of the list is shown as a preview on wide screens.
     */
    private function inbox(Request $request, ?Offer $chosen): Response
    {
        $user = $request->user()->id;
        // Expiry and lost hiring access are applied when read, so every pending offer is brought up to date first.
        foreach ($this->mine($user)->where('status', 'pending')->get() as $pending) {
            OfferLifecycle::refresh($pending);
        }
        $query = $request->query('list');
        $list = is_string($query) && in_array($query, self::LISTS, true) ? $query : 'all';
        $page = null;
        $offers = function () use (&$page, $list, $user): LengthAwarePaginator {
            return $page ??= $this->listed($this->mine($user), $list, $user)
                ->with(['project:id,title,status', 'client:id,name', 'client.profile:id,user_id,company', 'freelancer:id,name', 'proposal:id,profile_snapshot', 'contract:id,offer_id,status,delivery_due_at'])
                ->orderByDesc('id')->paginate(20)->withQueryString();
        };
        $selected = $chosen !== null ? OfferLifecycle::refresh($chosen) : $offers()->getCollection()->first();
        $readOnly = ! $request->user()->canParticipateInMarketplace();

        return Inertia::render('offers/index', [
            'offers' => function () use ($offers, $user) {
                $rows = $offers();
                $latest = $this->latestOf($rows->getCollection()->map(fn (Offer $offer) => $offer->proposal_id)->values()->all());

                return $rows->through(fn (Offer $offer) => $this->row($offer, $user, $latest[$offer->proposal_id] === $offer->id));
            },
            'list' => $list,
            'counts' => fn () => collect(['all', ...self::LISTS])->mapWithKeys(fn (string $name) => [$name => $this->listed($this->mine($user), $name, $user)->count()])->all(),
            'offer' => $selected !== null ? $this->details($selected, $user, $readOnly) : null,
            'history' => $selected === null ? [] : Offer::query()->where('proposal_id', $selected->proposal_id)->orderByDesc('id')->get()
                ->map(fn (Offer $item) => [...$item->only(['id', 'status', 'version', 'reason', 'created_at', 'closed_at']),
                    'amount' => $item->terms['amount'], 'duration_days' => $item->terms['duration_days']]),
            'preview' => $chosen === null,
            'readOnly' => $readOnly,
        ]);
    }

    public function update(Request $request, Offer $offer): RedirectResponse
    {
        abort_unless($offer->contains($request->user()->id), 404);
        $data = $request->validate(['action' => ['required', Rule::in(['accept', 'decline', 'withdraw', 'changes_requested'])],
            'version' => ['required', 'integer', 'min:1'], 'reason' => ['required_if:action,changes_requested', 'nullable', 'string', 'min:10', 'max:3000']]);
        abort_unless(($data['action'] === 'withdraw' ? $offer->client_id : $offer->freelancer_id) === $request->user()->id, 403);
        OfferLifecycle::refresh($offer);
        $result = DB::transaction(function () use ($offer, $data): Contract|Offer|null {
            OfferLifecycle::lockParticipants($offer->client_id, $offer->freelancer_id);
            Project::query()->lockForUpdate()->findOrFail($offer->project_id);
            $proposal = Proposal::query()->lockForUpdate()->findOrFail($offer->proposal_id);
            $current = Offer::query()->lockForUpdate()->findOrFail($offer->id);
            if ($current->status === 'accepted' && $data['action'] === 'accept') {
                return Contract::query()->where('offer_id', $current->id)->firstOrFail();
            }
            $target = match ($data['action']) {
                'decline' => 'declined', 'withdraw' => 'withdrawn', default => $data['action']
            };
            if ($current->status === $target && $current->version === (int) $data['version'] + 1 && $current->reason === ($data['reason'] ?? null)) {
                return $current;
            }
            if ($current->status !== 'pending' || $current->version !== (int) $data['version']) {
                return null;
            }
            if (! $current->expires_at->isFuture()) {
                OfferLifecycle::close($current, 'expired');

                return null;
            }
            if (! HiringAccess::writable($proposal)) {
                OfferLifecycle::close($current, 'restricted', 'hiring_unavailable');

                return null;
            }
            if ($data['action'] === 'accept') {
                $contract = OfferLifecycle::accept($current);
                $this->notify($current, 'offer_accepted', $current->client_id, $current->freelancer_id, '/contracts/'.$contract->id);

                return $contract;
            }
            OfferLifecycle::close($current, $target, $data['reason'] ?? null);
            // The client withdraws; every other response comes from the freelancer.
            $withdrawn = $data['action'] === 'withdraw';
            $this->notify($current, 'offer_'.$target, $withdrawn ? $current->freelancer_id : $current->client_id, $withdrawn ? $current->client_id : $current->freelancer_id, '/offers/'.$current->id);

            return $current;
        }, 3);
        if ($result === null) {
            throw ValidationException::withMessages(['offer' => __('This offer is no longer available. Reload to see its current status.')]);
        }

        return $result instanceof Contract ? to_route('contracts.show', $result) : to_route('offers.show', $offer);
    }

    private function notify(Offer $offer, string $kind, int $recipient, int $actor, string $href): void
    {
        $title = $offer->project->title;
        DB::afterCommit(fn () => User::query()->find($recipient)?->notify(new WorkspaceEvent($kind, $href, $title, User::query()->whereKey($actor)->value('name'))));
    }

    /** @return Builder<Offer> */
    private function mine(int $user): Builder
    {
        return Offer::query()->where(fn (Builder $query) => $query->where('client_id', $user)->orWhere('freelancer_id', $user));
    }

    /**
     * One list of the inbox. A pending offer waits for the freelancer; a change request waits for the client while it is
     * the latest offer on its proposal and the project is still published. Everything else that is not accepted is closed.
     *
     * @param  Builder<Offer>  $query
     * @return Builder<Offer>
     */
    private function listed(Builder $query, string $list, int $user): Builder
    {
        $answered = fn (Builder $query): Builder => $query->where('status', 'changes_requested')
            ->whereHas('project', fn (Builder $project) => $project->where('status', 'published'))
            ->whereNotExists(fn ($newer) => $newer->selectRaw('1')->from('offers as newer')
                ->whereColumn('newer.proposal_id', 'offers.proposal_id')->whereColumn('newer.id', '>', 'offers.id'));
        $turn = fn (string $waiting, string $answering): Builder => $query->where(fn (Builder $query) => $query
            ->where(fn (Builder $query) => $query->where($waiting, $user)->where('status', 'pending'))
            ->orWhere(fn (Builder $query) => $answered($query->where($answering, $user))));

        return match ($list) {
            'yours' => $turn('freelancer_id', 'client_id'),
            'theirs' => $turn('client_id', 'freelancer_id'),
            'accepted' => $query->where('status', 'accepted'),
            'closed' => $query->whereNotIn('status', ['pending', 'accepted'])->whereNot(fn (Builder $query) => $answered($query)),
            default => $query,
        };
    }

    /**
     * The newest offer on each of these proposals.
     *
     * @param  array<int, int>  $proposals
     * @return array<int, int>
     */
    private function latestOf(array $proposals): array
    {
        return Offer::query()->whereIn('proposal_id', $proposals)->groupBy('proposal_id')
            ->selectRaw('proposal_id, max(id) as latest')->pluck('latest', 'proposal_id')->map(fn ($id) => (int) $id)->all();
    }

    /** Whose move it is: the viewer's, the other side's, or nobody's once the offer is settled. */
    private function turn(Offer $offer, int $user, bool $latest): ?string
    {
        $client = $offer->client_id === $user;
        if ($offer->status === 'pending') {
            return $client ? 'theirs' : 'yours';
        }
        if ($offer->status === 'changes_requested' && $latest && $offer->project->status === 'published') {
            return $client ? 'yours' : 'theirs';
        }

        return null;
    }

    /**
     * The other side as the viewer met them: a client sees the freelancer's proposal profile, a freelancer the client's
     * name and company. Client photos are private.
     *
     * @return array{name: string|null, avatar: string|null, company: string|null}
     */
    private function counterpart(Offer $offer, int $user): array
    {
        if ($offer->client_id === $user) {
            $snapshot = $offer->proposal->profile_snapshot ?? [];

            return ['name' => $snapshot['name'] ?? $offer->freelancer->name, 'avatar' => $snapshot['avatar'] ?? null, 'company' => null];
        }

        return ['name' => $offer->client->name, 'avatar' => null, 'company' => $offer->client->profile?->company];
    }

    /** @return array<string, mixed> */
    private function row(Offer $offer, int $user, bool $latest): array
    {
        return [...$offer->only(['id', 'proposal_id', 'status', 'expires_at', 'closed_at', 'created_at']),
            'project' => $offer->project->only(['id', 'title']), 'amount' => $offer->terms['amount'], 'is_client' => $offer->client_id === $user,
            'counterpart' => $this->counterpart($offer, $user), 'contract' => $offer->contract?->only(['id', 'status']),
            'latest' => $latest, 'turn' => $this->turn($offer, $user, $latest)];
    }

    /** @return array<string, mixed> */
    private function details(Offer $offer, int $user, bool $readOnly): array
    {
        $offer->loadMissing(['project:id,title,status', 'client:id,name', 'client.profile:id,user_id,company', 'freelancer:id,name', 'proposal:id,profile_snapshot', 'contract:id,offer_id,status,delivery_due_at']);
        $latest = ! Offer::query()->where('proposal_id', $offer->proposal_id)->where('id', '>', $offer->id)->exists();
        // A replacement follows the rules of the offer form: the client's latest offer, hiring still open, no other pending offer.
        $replace = ! $readOnly && $offer->client_id === $user && $latest && in_array($offer->status, ['changes_requested', 'declined', 'withdrawn', 'expired'], true)
            && ! Offer::query()->where('pending_project_id', $offer->project_id)->exists()
            && HiringAccess::writable(Proposal::query()->findOrFail($offer->proposal_id));

        return [...$offer->only(['id', 'proposal_id', 'terms', 'status', 'version', 'expires_at', 'closed_at', 'reason', 'created_at']),
            'project' => $offer->project->only(['id', 'title']), 'is_client' => $offer->client_id === $user,
            'contract_id' => $offer->contract?->id, 'contract' => $offer->contract?->only(['id', 'status', 'delivery_due_at']),
            'counterpart' => $this->counterpart($offer, $user), 'latest' => $latest, 'turn' => $this->turn($offer, $user, $latest),
            'conversation_id' => Conversation::query()->where('proposal_id', $offer->proposal_id)->value('id'), 'can_replace' => $replace];
    }
}

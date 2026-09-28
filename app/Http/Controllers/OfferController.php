<?php

namespace App\Http\Controllers;

use App\Actions\Conversations\HiringAccess;
use App\Actions\Offers\OfferLifecycle;
use App\Models\Contract;
use App\Models\Offer;
use App\Models\Project;
use App\Models\Proposal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OfferController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user()->id;
        $offers = Offer::query()->where(fn ($q) => $q->where('client_id', $user)->orWhere('freelancer_id', $user))
            ->orderByDesc('id')->paginate(20)->through(fn (Offer $offer) => $this->details(OfferLifecycle::refresh($offer), $user));

        return Inertia::render('offers/index', ['offers' => $offers]);
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

            return $offer;
        }, 3);

        return to_route('offers.show', $offer);
    }

    public function show(Request $request, Offer $offer): Response
    {
        abort_unless($offer->contains($request->user()->id), 404);

        return Inertia::render('offers/show', ['offer' => $this->details(OfferLifecycle::refresh($offer), $request->user()->id),
            'history' => Offer::query()->where('proposal_id', $offer->proposal_id)->orderByDesc('id')->get()->map(fn (Offer $item) => $this->details(OfferLifecycle::refresh($item), $request->user()->id))]);
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
                return OfferLifecycle::accept($current);
            }
            OfferLifecycle::close($current, $target, $data['reason'] ?? null);

            return $current;
        }, 3);
        if ($result === null) {
            throw ValidationException::withMessages(['offer' => __('This offer is no longer available. Reload to see its current status.')]);
        }

        return $result instanceof Contract ? to_route('contracts.show', $result) : to_route('offers.show', $offer);
    }

    /** @return array<string, mixed> */
    private function details(Offer $offer, int $user): array
    {
        return [...$offer->only(['id', 'proposal_id', 'terms', 'status', 'version', 'expires_at', 'closed_at', 'reason', 'created_at']),
            'project' => $offer->project->only(['id', 'title']), 'is_client' => $offer->client_id === $user,
            'contract_id' => Contract::query()->where('offer_id', $offer->id)->value('id')];
    }
}

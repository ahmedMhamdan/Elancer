<?php

namespace App\Actions\Offers;

use App\Actions\Conversations\HiringAccess;
use App\Models\Contract;
use App\Models\Conversation;
use App\Models\Offer;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OfferLifecycle
{
    public static function close(Offer $offer, string $status, ?string $reason = null): void
    {
        $offer->forceFill(['status' => $status, 'reason' => $reason, 'pending_project_id' => null,
            'closed_at' => now(), 'version' => $offer->version + 1])->save();
    }

    // Call with the project locked. Expiry never depends on the scheduler.
    public static function expire(int $project): void
    {
        foreach (Offer::query()->where('project_id', $project)->where('status', 'pending')->where('expires_at', '<=', now())->lockForUpdate()->get() as $offer) {
            self::close($offer, 'expired');
        }
    }

    // Caller holds participant locks, shared with acceptance. Never revive these on unblock/reinstatement.
    public static function restrict(int $user, ?int $counterpart = null): void
    {
        $query = Offer::query()->where('status', 'pending')->where(fn ($q) => $q->where('client_id', $user)->orWhere('freelancer_id', $user));
        if ($counterpart !== null) {
            $query->where(fn ($q) => $q->where('client_id', $counterpart)->orWhere('freelancer_id', $counterpart));
        }
        foreach ($query->orderBy('id')->lockForUpdate()->get() as $offer) {
            self::close($offer, 'restricted', $counterpart === null ? 'account_restricted' : 'contact_blocked');
        }
    }

    public static function refresh(Offer $offer): Offer
    {
        return DB::transaction(function () use ($offer): Offer {
            self::lockParticipants($offer->client_id, $offer->freelancer_id);
            Project::query()->lockForUpdate()->findOrFail($offer->project_id);
            $current = Offer::query()->lockForUpdate()->findOrFail($offer->id);
            if ($current->status === 'pending') {
                if ($current->expires_at->isPast() || $current->expires_at->equalTo(now())) {
                    self::close($current, 'expired');
                } elseif (! HiringAccess::writable(Proposal::query()->findOrFail($current->proposal_id))) {
                    self::close($current, 'restricted', 'hiring_unavailable');
                }
            }

            return $current;
        }, 3);
    }

    public static function lockParticipants(int $client, int $freelancer): void
    {
        $participants = User::query()->whereIn('id', [$client, $freelancer])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        abort_unless($client !== $freelancer && $participants->has($client) && $participants->has($freelancer), 409);
    }

    public static function accept(Offer $offer): Contract
    {
        $conversation = Conversation::query()->where('proposal_id', $offer->proposal_id)->first();
        if (! $conversation) {
            $conversation = new Conversation;
            $conversation->forceFill(['proposal_id' => $offer->proposal_id, 'client_id' => $offer->client_id, 'freelancer_id' => $offer->freelancer_id])->save();
        }
        $contract = new Contract;
        $contract->forceFill([
            'project_id' => $offer->project_id, 'proposal_id' => $offer->proposal_id, 'offer_id' => $offer->id,
            'client_id' => $offer->client_id, 'freelancer_id' => $offer->freelancer_id, 'conversation_id' => $conversation->id,
            'agreement' => [...$offer->terms, 'project_title' => $offer->project->title,
                'client_name' => User::query()->findOrFail($offer->client_id)->name,
                'freelancer_name' => User::query()->findOrFail($offer->freelancer_id)->name,
                'accepted_at' => now()->toIso8601String()],
        ])->save();
        self::close($offer, 'accepted');
        $offer->project->forceFill(['status' => 'hired', 'version' => $offer->project->version + 1])->save();
        $conversation->forceFill(['client_archived' => false, 'freelancer_archived' => false])->touch();

        return $contract;
    }
}

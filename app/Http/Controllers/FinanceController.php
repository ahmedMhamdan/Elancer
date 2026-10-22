<?php

namespace App\Http\Controllers;

use App\Actions\Payments\ContractFunding;
use App\Models\Contract;
use App\Models\PaymentAttempt;
use App\Models\PaymentEvent;
use App\Payments\Gateways;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinanceController extends Controller
{
    /** Finance overview (plan 032 part 12, Ahmed's pick "A"): the money on the member's contracts, never a balance. */
    public function __invoke(Request $request, Gateways $gateways): Response
    {
        $user = $request->user()->id;
        // Contracts that wait for funding: the client's to fund first, then the freelancer's to wait for, newest first;
        // at most ten, with the full count so the page can point to Contracts for the rest.
        $waiting = fn () => $this->mine($user)->where('status', 'awaiting_payment');
        $awaiting = $waiting()->orderByRaw('case when client_id = ? then 0 else 1 end', [$user])->orderByDesc('id')->limit(10)->get();
        $latest = PaymentAttempt::query()->whereIn('contract_id', $awaiting->pluck('id'))->orderBy('id')->get()->keyBy('contract_id');

        return Inertia::render('finance/index', [
            'summary' => ContractFunding::summary($user),
            'providers' => $gateways->available(),
            'awaiting' => $awaiting->map(fn (Contract $contract): array => [
                'id' => $contract->id, 'conversation_id' => $contract->conversation_id, 'created_at' => $contract->created_at,
                'project_title' => $contract->agreement['project_title'] ?? null, 'amount' => $contract->agreement['amount'],
                'counterpart' => $contract->client_id === $user ? $contract->agreement['freelancer_name'] ?? null : $contract->agreement['client_name'] ?? null,
                'is_client' => $contract->client_id === $user, 'payment' => $latest->get($contract->id)?->summary(),
            ]),
            'awaiting_count' => $waiting()->count(),
            'attempts' => $this->attempts($user)->limit(5)->get()->map($this->attempt(...)),
            'payments_count' => $this->attempts($user)->count(),
        ]);
    }

    /** Every payment attempt on the member's own contracts, newest first. */
    public function payments(Request $request, Gateways $gateways): Response
    {
        return Inertia::render('finance/payments', [
            'attempts' => $this->attempts($request->user()->id)->paginate(15)->through($this->attempt(...)),
            'providers' => $gateways->available(),
        ]);
    }

    /** @return Builder<Contract> */
    private function mine(int $user): Builder
    {
        return Contract::query()->where(fn ($q) => $q->where('client_id', $user)->orWhere('freelancer_id', $user));
    }

    /** @return Builder<PaymentAttempt> */
    private function attempts(int $user): Builder
    {
        // Newest first by when the payment started (seeded and imported rows need not follow the id order). The
        // refund's time is its recorded refund event (CancelContract writes one per verified refund).
        return PaymentAttempt::query()->with('contract')->orderByDesc('payment_attempts.created_at')->orderByDesc('payment_attempts.id')
            ->addSelect(['refunded_at' => PaymentEvent::query()->select('created_at')->whereColumn('payment_attempt_id', 'payment_attempts.id')
                ->where('type', 'refund')->orderBy('id')->limit(1)])
            ->withCasts(['refunded_at' => 'immutable_datetime'])
            ->whereIn('contract_id', $this->mine($user)->select('id'));
    }

    /** @return array<string, mixed> */
    private function attempt(PaymentAttempt $attempt): array
    {
        return [...$attempt->summary(), 'contract_id' => $attempt->contract_id, 'project_title' => $attempt->contract->agreement['project_title'] ?? null,
            'refunded_at' => $attempt->getAttribute('refunded_at')];
    }
}

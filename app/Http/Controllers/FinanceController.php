<?php

namespace App\Http\Controllers;

use App\Actions\Payments\ContractFunding;
use App\Models\Contract;
use App\Models\PaymentAttempt;
use App\Payments\Gateways;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinanceController extends Controller
{
    public function __invoke(Request $request, Gateways $gateways): Response
    {
        $user = $request->user()->id;
        $mine = fn () => Contract::query()->where(fn ($q) => $q->where('client_id', $user)->orWhere('freelancer_id', $user));
        $contracts = $mine()->orderByDesc('id')->paginate(10);
        $latest = PaymentAttempt::query()->whereIn('contract_id', $contracts->pluck('id'))->orderBy('id')->get()->keyBy('contract_id');

        return Inertia::render('finance/index', [
            'summary' => ContractFunding::summary($user),
            'providers' => $gateways->available(),
            'contracts' => $contracts->through(fn (Contract $contract) => [
                ...$contract->only(['id', 'status', 'funded_at', 'delivery_due_at']),
                'project_title' => $contract->agreement['project_title'] ?? null, 'amount' => $contract->agreement['amount'],
                'counterpart' => $contract->client_id === $user ? $contract->agreement['freelancer_name'] ?? null : $contract->agreement['client_name'] ?? null,
                'is_client' => $contract->client_id === $user, 'payment' => $latest->get($contract->id)?->summary(),
            ]),
            'attempts' => $this->attempts($user)->limit(10)->get()->map($this->attempt(...)),
        ]);
    }

    /** Every payment attempt on the member's own contracts, newest first. */
    public function payments(Request $request): Response
    {
        return Inertia::render('finance/payments', [
            'attempts' => $this->attempts($request->user()->id)->paginate(15)->through($this->attempt(...)),
        ]);
    }

    /** @return Builder<PaymentAttempt> */
    private function attempts(int $user): Builder
    {
        return PaymentAttempt::query()->with('contract')->orderByDesc('id')->whereIn('contract_id',
            Contract::query()->select('id')->where(fn ($q) => $q->where('client_id', $user)->orWhere('freelancer_id', $user)));
    }

    /** @return array<string, mixed> */
    private function attempt(PaymentAttempt $attempt): array
    {
        return [...$attempt->summary(), 'contract_id' => $attempt->contract_id, 'project_title' => $attempt->contract->agreement['project_title'] ?? null];
    }
}

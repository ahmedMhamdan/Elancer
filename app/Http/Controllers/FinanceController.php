<?php

namespace App\Http\Controllers;

use App\Actions\Payments\ContractFunding;
use App\Models\Contract;
use App\Models\PaymentAttempt;
use App\Payments\Gateways;
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
            'attempts' => PaymentAttempt::query()->whereIn('contract_id', $mine()->select('id'))->with('contract')->orderByDesc('id')->limit(10)->get()
                ->map(fn (PaymentAttempt $attempt) => [...$attempt->summary(), 'contract_id' => $attempt->contract_id,
                    'project_title' => $attempt->contract->agreement['project_title'] ?? null]),
        ]);
    }
}

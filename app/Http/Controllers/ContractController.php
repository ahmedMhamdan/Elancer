<?php

namespace App\Http\Controllers;

use App\Actions\Payments\ContractFunding;
use App\Models\Contract;
use App\Models\PaymentAttempt;
use App\Payments\Gateways;
use Illuminate\Http\Request;
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

        return Inertia::render('contracts/show', [
            'contract' => $this->details($contract, $user),
            'payment' => PaymentAttempt::query()->where('contract_id', $contract->id)->latest('id')->first()?->summary(),
            'providers' => $contract->client_id === $user ? $gateways->available() : [],
            'funding_paused' => ContractFunding::paused($contract),
        ]);
    }

    /** @return array<string, mixed> */
    private function details(Contract $contract, int $user): array
    {
        return [...$contract->only(['id', 'project_id', 'offer_id', 'conversation_id', 'status', 'agreement', 'created_at', 'funded_at', 'delivery_due_at']),
            'is_client' => $contract->client_id === $user];
    }
}

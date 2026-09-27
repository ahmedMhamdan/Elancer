<?php

namespace App\Http\Controllers;

use App\Models\Contract;
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
            ->orderByDesc('id')->paginate(20)->through(fn (Contract $contract) => $this->details($contract))]);
    }

    public function show(Request $request, Contract $contract): Response
    {
        abort_unless($contract->contains($request->user()->id), 404);

        return Inertia::render('contracts/show', ['contract' => $this->details($contract)]);
    }

    /** @return array<string, mixed> */
    private function details(Contract $contract): array
    {
        return $contract->only(['id', 'project_id', 'offer_id', 'conversation_id', 'status', 'agreement', 'created_at']);
    }
}

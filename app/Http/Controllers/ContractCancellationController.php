<?php

namespace App\Http\Controllers;

use App\Actions\Contracts\CancelContract;
use App\Models\Contract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContractCancellationController extends Controller
{
    public function __construct(private CancelContract $cancellation) {}

    public function store(Request $request, Contract $contract): RedirectResponse
    {
        abort_unless($contract->contains($request->user()->id), 404);
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:3000']]);
        $this->cancellation->request($contract, $request->user()->id, $data['reason']);

        return to_route('contracts.show', $contract);
    }

    public function update(Request $request, Contract $contract): RedirectResponse
    {
        abort_unless($contract->contains($request->user()->id), 404);
        $data = $request->validate(['action' => ['required', Rule::in(['accept', 'decline', 'withdraw'])]]);
        $this->cancellation->respond($contract, $request->user()->id, $data['action']);

        return to_route('contracts.show', $contract);
    }

    /** Either participant may ask the provider again after a pending or failed refund. */
    public function refund(Request $request, Contract $contract): RedirectResponse
    {
        abort_unless($contract->contains($request->user()->id), 404);
        $this->cancellation->refund($contract);

        return to_route('contracts.show', $contract);
    }
}

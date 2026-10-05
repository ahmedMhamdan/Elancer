<?php

namespace App\Http\Controllers;

use App\Actions\Contracts\ContractAmendments;
use App\Models\Contract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContractAmendmentController extends Controller
{
    public function store(Request $request, Contract $contract): RedirectResponse
    {
        abort_unless($contract->contains($request->user()->id), 404);
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:3000'],
            'due_at' => ['nullable', 'required_without:extra_rounds', 'date', 'after:now', 'before:'.now()->addYear()->toIso8601String()],
            'extra_rounds' => ['nullable', 'required_without:due_at', 'integer', 'min:1', 'max:5'],
        ]);
        ContractAmendments::propose($contract, $request->user()->id, $data);

        return to_route('contracts.show', $contract);
    }

    public function update(Request $request, Contract $contract): RedirectResponse
    {
        abort_unless($contract->contains($request->user()->id), 404);
        $data = $request->validate(['amendment' => ['required', 'integer'], 'action' => ['required', Rule::in(['accept', 'decline', 'withdraw'])]]);
        ContractAmendments::respond($contract, $request->user()->id, (int) $data['amendment'], $data['action']);

        return to_route('contracts.show', $contract);
    }
}

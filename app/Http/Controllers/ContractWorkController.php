<?php

namespace App\Http\Controllers;

use App\Actions\Contracts\ContractWork;
use App\Models\Contract;
use App\Models\ContractSubmissionFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ContractWorkController extends Controller
{
    public function deliver(Request $request, Contract $contract): RedirectResponse
    {
        $this->participant($request, $contract, $contract->freelancer_id);
        $data = $request->validate([
            'message' => ['required', 'string', 'min:20', 'max:10000'],
            'links' => ['nullable', 'array', 'list', 'max:5'],
            'links.*' => ['required', 'string', 'max:2000', 'url:http,https', 'distinct'],
            'files' => ['nullable', 'array', 'list', 'max:3'],
            'files.*' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf,txt,zip'],
            // Q49: a formal delivery is the complete agreed set, never a part.
            'complete' => ['accepted'],
            'client_token' => ['required', 'uuid'],
        ]);
        ContractWork::submit($contract, $data, array_values($request->file('files') ?? []));

        return to_route('contracts.show', $contract);
    }

    public function revise(Request $request, Contract $contract): RedirectResponse
    {
        $this->participant($request, $contract, $contract->client_id);
        $data = $request->validate(['submission' => ['required', 'integer'], 'changes' => ['required', 'string', 'min:20', 'max:10000']]);
        ContractWork::requestRevision($contract, (int) $data['submission'], $data['changes']);

        return to_route('contracts.show', $contract);
    }

    public function complete(Request $request, Contract $contract): RedirectResponse
    {
        $this->participant($request, $contract, $contract->client_id);
        $data = $request->validate(['submission' => ['required', 'integer']]);
        ContractWork::complete($contract, (int) $data['submission']);

        return to_route('contracts.show', $contract);
    }

    public function review(Request $request, Contract $contract): RedirectResponse
    {
        abort_unless($contract->contains($request->user()->id), 404);
        $data = $request->validate(['rating' => ['required', 'integer', 'min:1', 'max:5'], 'body' => ['required', 'string', 'min:20', 'max:3000']]);
        ContractWork::review($contract, $request->user()->id, (int) $data['rating'], $data['body']);

        return to_route('contracts.show', $contract);
    }

    /** Delivery files are private to the two participants. */
    public function file(Request $request, Contract $contract, ContractSubmissionFile $file): Response
    {
        abort_unless($contract->contains($request->user()->id) && $file->submission->contract_id === $contract->id && Storage::disk('uploads')->exists($file->path), 404);

        return Storage::disk('uploads')->download($file->path, $file->name, ['Content-Type' => 'application/octet-stream', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function participant(Request $request, Contract $contract, int $role): void
    {
        abort_unless($contract->contains($request->user()->id), 404);
        abort_unless($role === $request->user()->id, 403);
    }
}

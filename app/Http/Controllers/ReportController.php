<?php

namespace App\Http\Controllers;

use App\Actions\Reports\Reports;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /** Q66: the reporter's own text, the status and a brief outcome. Never notes, evidence or the reviewer. */
    public function index(Request $request): Response
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1', 'max:100000']]);

        return Inertia::render('reports/index', [
            'reports' => Report::query()->where('reporter_id', $request->user()->id)->orderByDesc('id')->paginate(10)
                ->through(fn (Report $report) => [
                    ...$report->only(['id', 'target_type', 'reason', 'explanation', 'status', 'outcome', 'created_at', 'resolved_at']),
                    'title' => $report->snapshot['title'] ?? '',
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'target_type' => ['required', Rule::in(Report::TARGETS)],
            'target_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', Rule::in(Report::REASONS)],
            'explanation' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        Reports::file($request->user(), $data['target_type'], (int) $data['target_id'], $data['reason'], $data['explanation']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Report sent. You can follow it under My reports.')]);

        return back();
    }
}

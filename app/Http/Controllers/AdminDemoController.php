<?php

namespace App\Http\Controllers;

use App\Actions\Auth\DemoAccess;
use App\Actions\Demo\DemoBaseline;
use App\Actions\Demo\DemoResetRefused;
use App\Actions\Demo\DemoWorld;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Plan 027: super administrators save the demo accounts' current rows as the starting point and
 * reset the demo to it. Both rewrite many rows at once and both are recorded.
 */
class AdminDemoController extends Controller
{
    public function index(Request $request): Response
    {
        $baseline = DemoWorld::baseline();
        $summary = $baseline === null ? [] : (array) json_decode((string) $baseline->summary, true);
        $events = DB::table('moderation_events')->where('target_type', 'demo')->orderByDesc('id')->limit(10)->get();
        $people = User::query()->whereIn('id', $events->pluck('actor_id')->push($baseline?->taken_by)->filter()->unique())->pluck('name', 'id');
        $time = fn (mixed $value): ?string => $value === null ? null : Carbon::parse((string) $value)->toIso8601String();

        return Inertia::render('admin/demo/index', [
            'accounts' => count(DemoWorld::accounts()),
            'entry' => DemoAccess::roles(),
            'actions' => (bool) config('demo.actions'),
            'rules' => ['idle_minutes' => (int) config('demo.reset.idle_minutes'), 'max_hours' => (int) config('demo.reset.max_hours')],
            'baseline' => $baseline === null ? null : [
                'taken_at' => $time($baseline->taken_at), 'taken_by' => $people->get($baseline->taken_by),
                'accounts' => (int) ($summary['accounts'] ?? 0), 'rows' => (int) array_sum((array) ($summary['tables'] ?? [])),
                'changed_at' => $time($baseline->changed_at), 'restored_at' => $time($baseline->restored_at), 'problem' => $baseline->problem,
            ],
            'events' => $events->map(fn ($event) => ['id' => $event->id, 'action' => $event->action, 'actor' => $people->get($event->actor_id),
                'details' => (array) json_decode((string) $event->reason, true), 'created_at' => $time($event->created_at)]),
            'notice' => $request->session()->get('demo_notice'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->answer(function () use ($request): string {
            DemoBaseline::save($request->user());

            return 'saved';
        });
    }

    public function restore(Request $request): RedirectResponse
    {
        return $this->answer(function () use ($request): string {
            DemoBaseline::restore($request->user());

            return 'reset';
        });
    }

    /** @param  \Closure(): string  $action */
    private function answer(\Closure $action): RedirectResponse
    {
        try {
            $notice = $action();
        } catch (DemoResetRefused $refusal) {
            $notice = 'refused|'.$refusal->getMessage();
        }

        return back(303, [], route('admin.demo.index'))->with('demo_notice', $notice);
    }
}

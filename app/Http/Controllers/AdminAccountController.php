<?php

namespace App\Http\Controllers;

use App\Actions\Accounts\AccountModeration;
use App\Enums\AccountStatus;
use App\Models\Contract;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminAccountController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['all', 'suspended'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);
        $search = trim($filters['q'] ?? '');
        $status = $filters['status'] ?? 'all';
        $query = User::query();
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('email', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%'));
        }
        if ($status === 'suspended') {
            $query->where('status', AccountStatus::Suspended->value);
        }

        return Inertia::render('admin/accounts/index', [
            // Explicit selection prevents sharing auth secrets or unrelated profile data.
            'users' => $query->orderBy('id')->paginate(10, ['id', 'name', 'email', 'status', 'email_verified_at', 'is_admin', 'is_super_admin'])->withQueryString(),
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function show(Request $request, User $user): Response
    {
        $actor = $request->user();
        $report = $this->report($request, $actor, $user);
        $events = DB::table('moderation_events')->where('subject_id', $user->id)->whereIn('action', ['account_suspended', 'account_reinstated'])->orderByDesc('id')->limit(50)->get();
        $people = User::query()->whereIn('id', $events->pluck('actor_id')->filter()->unique())->pluck('name', 'id');
        $manages = AccountModeration::manages($actor, $user);

        return Inertia::render('admin/accounts/show', [
            'account' => [
                ...$user->only(['id', 'name', 'email', 'is_admin', 'is_super_admin', 'email_verified_at', 'created_at', 'suspension_reason', 'suspended_at']),
                'status' => $user->status->value,
                // Counts only: an account page never opens a member's contracts or conversations.
                'contracts' => Contract::query()->where(fn ($q) => $q->where('client_id', $user->id)->orWhere('freelancer_id', $user->id))
                    ->whereNotIn('status', ['completed', 'cancelled'])->count(),
                'reports' => Report::query()->where('subject_id', $user->id)
                    ->where(fn ($q) => $q->whereNull('reporter_id')->orWhere('reporter_id', '!=', $actor->id))->count(),
            ],
            'events' => $events->map(fn ($event) => ['id' => $event->id, 'action' => $event->action, 'actor' => $people->get($event->actor_id),
                'reason' => $event->reason, 'member_reason' => $event->member_reason, 'report_id' => $event->report_id,
                'created_at' => Carbon::parse((string) $event->created_at)->toIso8601String()]),
            'report' => $report?->id,
            'can' => [
                'suspend' => $manages && $user->status === AccountStatus::Active,
                'reinstate' => $manages && $user->status === AccountStatus::Suspended,
            ],
            'notice' => $request->session()->get('account_notice'),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['suspend', 'reinstate'])],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'member_reason' => ['required_if:action,suspend', 'nullable', 'string', 'min:10', 'max:500'],
            'report' => ['nullable', 'integer'],
        ]);
        $report = isset($data['report']) ? Report::query()->whereKey((int) $data['report'])->firstOrFail() : null;
        $data['action'] === 'suspend'
            ? AccountModeration::suspend($request->user(), $user, $data['reason'], (string) $data['member_reason'], $report)
            : AccountModeration::reinstate($request->user(), $user, $data['reason'], $report);

        return to_route('admin.accounts.show', ['user' => $user, ...($report ? ['report' => $report->id] : [])])
            ->with('account_notice', $data['action'] === 'suspend' ? 'suspended' : 'reinstated');
    }

    /** The report this visit came from, kept only when it is about this member and open to this administrator. */
    private function report(Request $request, User $actor, User $user): ?Report
    {
        $id = $request->validate(['report' => ['nullable', 'integer']])['report'] ?? null;
        $report = $id === null ? null : Report::query()->whereKey((int) $id)->first();

        return AccountModeration::linked($actor, $user, $report) ? $report : null;
    }
}

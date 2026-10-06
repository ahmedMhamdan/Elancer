<?php

namespace App\Http\Controllers;

use App\Actions\Portfolio\PortfolioCases;
use App\Models\Contract;
use App\Models\PortfolioApproval;
use App\Models\PortfolioCase;
use App\Models\Profile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PortfolioController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('portfolio/index', [
            'cases' => PortfolioCase::query()->where('user_id', $user->id)->with(['contract', 'approvals'])->orderByDesc('id')->get()->map(self::owned(...)),
            'contracts' => PortfolioCases::eligibleContracts($user->id)->orderByDesc('id')->get()
                ->map(fn (Contract $contract) => ['id' => $contract->id, 'title' => $contract->agreement['project_title'] ?? '']),
            'profilePublic' => Profile::query()->publiclyVisible()->where('user_id', $user->id)->exists(),
            'limit' => PortfolioCases::MAX_CASES,
        ]);
    }

    public function create(Request $request): Response
    {
        $contract = $request->integer('contract') ?: null;
        $linked = $contract === null ? null : PortfolioCases::eligibleContracts($request->user()->id)->whereKey($contract)->first();
        abort_if($contract !== null && ! $linked, 404);

        return Inertia::render('portfolio/edit', ['case' => null,
            'contract' => $linked ? ['id' => $linked->id, 'title' => $linked->agreement['project_title'] ?? ''] : null]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canParticipateInMarketplace(), 403);
        $contract = $request->validate(['contract' => ['nullable', 'integer']])['contract'] ?? null;
        $case = PortfolioCases::create($request->user(), $this->content($request), $contract === null ? null : (int) $contract);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Case study saved as a private draft.')]);

        return to_route('portfolio.edit', $case);
    }

    public function edit(Request $request, PortfolioCase $case): Response
    {
        abort_unless($case->user_id === $request->user()->id, 404);

        return Inertia::render('portfolio/edit', ['case' => self::owned($case->load(['contract', 'approvals'])), 'contract' => null]);
    }

    public function update(Request $request, PortfolioCase $case): RedirectResponse
    {
        abort_unless($case->user_id === $request->user()->id, 404);
        abort_unless($request->user()->canParticipateInMarketplace(), 403);
        PortfolioCases::update($case, $this->content($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Case study saved. The public version is unchanged.')]);

        return back();
    }

    public function act(Request $request, PortfolioCase $case): RedirectResponse
    {
        abort_unless($case->user_id === $request->user()->id, 404);
        $action = $request->validate(['action' => ['required', Rule::in(['publish', 'hide', 'show', 'request', 'withdraw'])]])['action'];
        // Hiding and withdrawing a request only ever reduce what is shared.
        abort_unless(in_array($action, ['hide', 'withdraw'], true) || $request->user()->canParticipateInMarketplace(), 403);
        PortfolioCases::act($case, $action);

        Inertia::flash('toast', ['type' => 'success', 'message' => [
            'publish' => __('Case study published.'), 'hide' => __('Case study hidden.'), 'show' => __('Case study is no longer hidden.'),
            'request' => __('Sent to the client for approval.'), 'withdraw' => __('Approval request withdrawn.'),
        ][$action]]);

        return back();
    }

    public function destroy(Request $request, PortfolioCase $case): RedirectResponse
    {
        abort_unless($case->user_id === $request->user()->id, 404);
        PortfolioCases::delete($case);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Case study deleted.')]);

        return to_route('portfolio.index');
    }

    /** Q52, Q53: only the client of the originating contract answers a request or withdraws permission. */
    public function consent(Request $request, Contract $contract): RedirectResponse
    {
        abort_unless($contract->contains($request->user()->id), 404);
        abort_unless($contract->client_id === $request->user()->id, 403);
        $data = $request->validate(['action' => ['required', Rule::in(['approve', 'decline', 'revoke'])], 'approval' => ['required_unless:action,revoke', 'nullable', 'integer']]);
        $data['action'] === 'revoke' ? PortfolioCases::revoke($contract) : PortfolioCases::respond($contract, (int) $data['approval'], $data['action']);

        return back();
    }

    public function show(PortfolioCase $case): Response
    {
        abort_unless(PortfolioCase::query()->publiclyVisible()->whereKey($case->id)->exists(), 404);
        $profile = Profile::query()->where('user_id', $case->user_id)->with('user')->firstOrFail();

        return Inertia::render('discovery/portfolio-case', [
            'case' => self::published($case),
            'freelancer' => ['id' => $profile->id, 'name' => $profile->user->name, 'headline' => $profile->headline],
        ]);
    }

    /**
     * Nothing from the contract is public: only that the work was completed here.
     *
     * @return array<string, mixed>
     */
    public static function published(PortfolioCase $case): array
    {
        return ['id' => $case->id, 'content' => $case->public_content, 'published_at' => $case->published_at, 'elancer_work' => $case->contract_id !== null];
    }

    /**
     * What the two contract participants see about a contract's case study. The client never
     * receives the private working copy, only what was sent to them and what they approved.
     *
     * @return array<string, mixed>|null
     */
    public static function forContract(Contract $contract, int $user): ?array
    {
        if ($contract->status !== 'completed') {
            return null;
        }
        $case = PortfolioCase::query()->where('contract_id', $contract->id)->with('approvals')->first();
        $client = $contract->client_id === $user;
        if ($client && (! $case || $case->approvals->isEmpty())) {
            return null;
        }
        $pending = $case?->approvals->firstWhere('status', 'pending');

        return [
            'case_id' => $client ? null : $case?->id,
            'pending' => $pending ? ['id' => $pending->id, 'content' => $pending->content, 'created_at' => $pending->created_at] : null,
            'public' => $case?->public_content,
            'hidden' => $case?->hidden_at !== null,
            'revoked_at' => $case?->revoked_at,
            'history' => self::history($case),
        ];
    }

    /** @return array<string, mixed> */
    private static function owned(PortfolioCase $case): array
    {
        $pending = $case->approvals->firstWhere('status', 'pending');

        return [
            ...$case->only(['id', 'content', 'public_content', 'published_at', 'hidden_at', 'revoked_at']),
            'changed' => $case->public_content !== null && $case->public_content != $case->content,
            'contract' => $case->contract ? ['id' => $case->contract->id, 'title' => $case->contract->agreement['project_title'] ?? ''] : null,
            'pending' => $pending !== null,
            'history' => self::history($case),
            'deletable' => $case->approvals->isEmpty(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function history(?PortfolioCase $case): array
    {
        return $case ? array_values($case->approvals->reverse()->map(fn (PortfolioApproval $approval) => $approval->only(['id', 'status', 'created_at', 'decided_at']))->all()) : [];
    }

    /** @return array<string, mixed> */
    private function content(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:5', 'max:120'],
            'summary' => ['required', 'string', 'min:20', 'max:300'],
            'body' => ['required', 'string', 'min:50', 'max:5000'],
            'skills' => ['present', 'array', 'list', 'max:15'],
            'skills.*' => ['string', 'distinct', Rule::exists('skills', 'name')],
            'links' => ['present', 'array', 'list', 'max:5'],
            'links.*' => ['array:label,url'],
            'links.*.label' => ['required', 'string', 'max:80'],
            'links.*.url' => ['required', 'url:https', 'max:2000'],
        ]);

        return ['title' => $data['title'], 'summary' => $data['summary'], 'body' => $data['body'], 'skills' => array_values($data['skills']), 'links' => array_values($data['links'])];
    }
}

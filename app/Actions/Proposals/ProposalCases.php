<?php

namespace App\Actions\Proposals;

use App\Models\PortfolioApproval;
use App\Models\PortfolioCase;
use App\Models\Proposal;
use App\Models\ProposalCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Plan 031 (Q37, Q55): case studies chosen as samples. Submitting copies each one's public version into the
 * proposal, so the client reads what was sent even after the freelancer hides or changes the case. Client-owned
 * work stays shown to recipients only while its client's approval of that exact content stands.
 */
class ProposalCases
{
    public const LIMIT = 3;

    /**
     * Cases with a public version their owner has not hidden and moderation has not hidden.
     *
     * @param  Builder<PortfolioCase>  $query
     * @return Builder<PortfolioCase>
     */
    public static function shareable(Builder $query): Builder
    {
        return $query->whereNotNull('public_content')->whereNull('hidden_at')->whereNull('moderated_at');
    }

    /**
     * The apply form's choices: every case of the member, and whether it can be sent now.
     *
     * @return list<array{id: int, title: string, summary: string, elancer_work: bool, shareable: bool}>
     */
    public static function options(int $user): array
    {
        $shareable = self::shareable(PortfolioCase::query()->where('user_id', $user))->pluck('id')->all();

        return array_values(PortfolioCase::query()->where('user_id', $user)->orderByDesc('id')->get()->map(fn (PortfolioCase $case) => [
            'id' => $case->id,
            'title' => (string) (($case->public_content ?? $case->content)['title'] ?? ''),
            'summary' => (string) (($case->public_content ?? $case->content)['summary'] ?? ''),
            'elancer_work' => $case->contract_id !== null,
            'shareable' => in_array($case->id, $shareable, true),
        ])->all());
    }

    /**
     * Copies the chosen cases' public versions for a submission. A case unchanged since its last copy on this
     * proposal keeps that copy. Call inside the submission's transaction.
     *
     * @param  list<int>  $cases
     * @return list<int> the copies, in the chosen order
     */
    public static function freeze(Proposal $proposal, array $cases): array
    {
        if ($cases === []) {
            return [];
        }
        $locked = PortfolioCase::query()->whereKey($cases)->where('user_id', $proposal->user_id)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $shareable = self::shareable(PortfolioCase::query()->whereKey($cases))->pluck('id')->all();
        $copies = [];
        foreach ($cases as $id) {
            $case = $locked->get($id);
            if (! $case || ! in_array($id, $shareable, true)) {
                $title = $case ? (($case->public_content ?? $case->content)['title'] ?? '') : '';
                throw ValidationException::withMessages(['cases' => __('":title" is not public now, so it cannot be sent. Remove it from your samples or publish it first.', ['title' => $title])]);
            }
            $last = ProposalCase::query()->where('proposal_id', $proposal->id)->where('portfolio_case_id', $id)->orderByDesc('id')->first();
            if ($last && $last->content == $case->public_content) {
                $copies[] = $last->id;

                continue;
            }
            $copy = new ProposalCase;
            $copy->forceFill(['proposal_id' => $proposal->id, 'portfolio_case_id' => $id, 'content' => $case->public_content])->save();
            $copies[] = $copy->id;
        }

        return $copies;
    }

    /**
     * The copies a submitted version names, as one reader may see them. A recipient gets only a notice for a copy
     * that is withheld; the author sees it, marked.
     *
     * @param  array<string, mixed>|null  $content
     * @return list<array<string, mixed>>
     */
    public static function resolve(Proposal $proposal, ?array $content, bool $author): array
    {
        $ids = array_values(array_filter((array) ($content['shared'] ?? []), 'is_int'));
        if ($ids === []) {
            return [];
        }
        $copies = ProposalCase::query()->whereKey($ids)->where('proposal_id', $proposal->id)->with('case')->get()->keyBy('id');
        $approved = self::approvedContent($copies->pluck('case')->filter()->values());

        return array_values(array_filter(array_map(function (int $id) use ($copies, $approved, $author): ?array {
            $copy = $copies->get($id);
            if (! $copy) {
                return null;
            }
            $withheld = ! self::visible($copy, $approved);
            if ($withheld && ! $author) {
                return ['id' => $copy->id, 'withheld' => true];
            }

            return self::view($copy->content, $copy->case->contract_id !== null, $withheld)
                + ['id' => $copy->id, 'case_id' => $author ? $copy->portfolio_case_id : null];
        }, $ids)));
    }

    /**
     * The author's preview of a draft: the current public version of each chosen case.
     *
     * @param  array<string, mixed>|null  $draft
     * @return list<array<string, mixed>>
     */
    public static function preview(Proposal $proposal, ?array $draft): array
    {
        $ids = array_values(array_filter((array) ($draft['cases'] ?? []), 'is_int'));
        $cases = PortfolioCase::query()->whereKey($ids)->where('user_id', $proposal->user_id)->get()->keyBy('id');

        return array_values(array_filter(array_map(function (int $id) use ($cases): ?array {
            $case = $cases->get($id);
            if (! $case || $case->public_content === null) {
                return null;
            }

            return self::view($case->public_content, $case->contract_id !== null, false) + ['id' => null, 'case_id' => $case->id];
        }, $ids)));
    }

    /**
     * Whether a copy may be shown to the proposal's client now.
     *
     * @param  Collection<int, list<array<string, mixed>>>|null  $approved
     */
    public static function visible(ProposalCase $copy, ?Collection $approved = null): bool
    {
        $case = $copy->case;
        // Q68: what moderation hid is hidden from proposal recipients too.
        if ($case->moderated_at !== null) {
            return false;
        }
        if ($case->contract_id === null) {
            return true;
        }
        // Q55: client-owned work follows its client's approval of this exact content; a withdrawal ends every approval.
        $approved ??= self::approvedContent(collect([$case]));

        foreach ($approved->get($case->id, []) as $content) {
            if ($content == $copy->content) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, PortfolioCase>  $cases
     * @return Collection<int, list<array<string, mixed>>>
     */
    private static function approvedContent(Collection $cases): Collection
    {
        $ids = $cases->whereNotNull('contract_id')->pluck('id')->all();

        return $ids === [] ? collect() : PortfolioApproval::query()->whereIn('portfolio_case_id', $ids)->where('status', 'approved')->get()
            ->groupBy('portfolio_case_id')->map(fn (Collection $approvals) => $approvals->map(fn (PortfolioApproval $approval) => (array) $approval->content)->values()->all());
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private static function view(array $content, bool $elancerWork, bool $withheld): array
    {
        return [
            'title' => (string) ($content['title'] ?? ''), 'summary' => (string) ($content['summary'] ?? ''), 'body' => (string) ($content['body'] ?? ''),
            'skills' => array_values((array) ($content['skills'] ?? [])), 'links' => array_values((array) ($content['links'] ?? [])),
            'images' => array_map(fn (array $image) => ['id' => (int) $image['id'], 'alt' => (string) ($image['alt'] ?? '')], array_values((array) ($content['images'] ?? []))),
            'elancer_work' => $elancerWork, 'withheld' => $withheld,
        ];
    }
}

<?php

namespace App\Actions\Contracts;

use App\Actions\Demo\DemoWorld;
use App\Models\Contract;
use App\Models\ContractReview;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Q18/Q78: what anyone may learn from a member's completed contracts and published reviews.
 * A member is read in one role at a time: 'freelancer' is what clients wrote about their work,
 * 'client' is what freelancers wrote about their hiring. The two never mix.
 */
class PublicReviews
{
    /**
     * Completed contracts and the published rating of each member, for a whole page of them at once.
     *
     * @param  array<int, int>  $members
     * @return array<int, array{completed: int, rating: array{count: int, average: float|null}}>
     */
    public static function records(array $members, string $role): array
    {
        $side = self::side($role);
        $finished = Contract::query()->toBase()->whereIn($side, $members)->where('status', 'completed');
        // Plan 027: what demo accounts finished or wrote after the saved starting point counts inside the demo only.
        DemoWorld::keepInside($finished, 'client_id', 'completed_at');
        $completed = $finished->groupBy($side)->select($side)->selectRaw('count(*) as contract_total')->pluck('contract_total', $side);
        $ratings = self::published($members, $role)->toBase()->groupBy('contract_reviews.subject_id')
            ->selectRaw('contract_reviews.subject_id as member_id, count(*) as review_total, sum(contract_reviews.rating) as star_total')->get()->keyBy('member_id');
        $records = [];
        foreach ($members as $member) {
            $reviews = (int) ($ratings[$member]->review_total ?? 0);
            $records[$member] = ['completed' => (int) ($completed[$member] ?? 0), 'rating' => ['count' => $reviews,
                // One decimal, halves rounded up, in whole numbers so every database gives the same figure.
                'average' => $reviews === 0 ? null : intdiv(20 * (int) $ratings[$member]->star_total + $reviews, 2 * $reviews) / 10.0]];
        }

        return $records;
    }

    /** @return array{completed: int, rating: array{count: int, average: float|null}} */
    public static function record(int $member, string $role): array
    {
        return self::records([$member], $role)[$member];
    }

    /**
     * The newest published reviews of one member, with the author's first name and nothing that leads to an account.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function latest(int $member, string $role, int $limit): array
    {
        $reviews = self::published([$member], $role)->select('contract_reviews.*')->with('contract')->orderByDesc('contract_reviews.id')->limit($limit)->get();
        // A project that left public pages no longer lends its title to a review.
        $public = Project::query()->visible()->whereIn('id', $reviews->pluck('contract.project_id'))->pluck('id')->all();
        $author = $role === 'client' ? 'freelancer_name' : 'client_name';

        return $reviews->map(fn (ContractReview $review) => [
            'id' => $review->id, 'rating' => $review->rating, 'body' => $review->body, 'created_at' => $review->created_at,
            'author' => Str::before(trim((string) ($review->contract->agreement[$author] ?? '')), ' '),
            'project_title' => in_array($review->contract->project_id, $public, true) ? $review->contract->agreement['project_title'] ?? null : null,
        ])->all();
    }

    /**
     * ContractWork::reviewsPublished() as a query: the contract is completed and both reviews exist or the hidden period is over.
     *
     * @param  array<int, int>  $members
     * @return Builder<ContractReview>
     */
    private static function published(array $members, string $role): Builder
    {
        $reviews = ContractReview::query()->join('contracts', 'contracts.id', '=', 'contract_reviews.contract_id')
            ->whereIn('contract_reviews.subject_id', $members)->whereColumn('contracts.'.self::side($role), 'contract_reviews.subject_id')
            ->whereNotNull('contracts.completed_at')
            ->where(fn (Builder $query) => $query->where('contracts.completed_at', '<', now()->subDays(ContractWork::REVIEW_DAYS))
                ->orWhereRaw('(select count(*) from contract_reviews as pair where pair.contract_id = contracts.id) >= 2'));
        DemoWorld::keepInside($reviews, 'contract_reviews.author_id', 'contract_reviews.created_at');

        return $reviews;
    }

    /** The contract column that holds the member in this role. */
    private static function side(string $role): string
    {
        return $role === 'client' ? 'client_id' : 'freelancer_id';
    }
}

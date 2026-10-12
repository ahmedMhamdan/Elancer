// Q18/Q82: a review's stars, a member's published rating and a list of published reviews.
// No TailAdmin source has a rating display, so these are Elancer's own, on the star style the contract workroom introduced.
import { Star } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';
import '../../css/elancer-reviews.css';

// The average of published ratings to one decimal, and exactly how many there are.
export type Rating = { count: number; average: number | null };
// Q78: the author is a first name only; nothing here leads to an account.
export type PublicReview = {
    id: number;
    rating: number;
    body: string;
    created_at: string;
    author: string;
    // Null once the project is no longer on public pages.
    project_title: string | null;
};

export function Stars({ rating }: { rating: number }) {
    const { t, locale } = useTranslation();
    return (
        <span
            className="review-stars"
            role="img"
            aria-label={t(':rating out of 5', {
                rating: rating.toLocaleString(locale),
            })}
        >
            {[1, 2, 3, 4, 5].map((star) => (
                <Star
                    key={star}
                    size={18}
                    aria-hidden="true"
                    className={star <= rating ? 'review-star-on' : ''}
                />
            ))}
        </span>
    );
}

export function CompletedProjects({ count }: { count: number }) {
    const { t } = useTranslation();
    return (
        <>
            {count === 0
                ? t('No completed projects yet')
                : count === 1
                  ? t('1 completed project')
                  : t(':count completed projects', { count })}
        </>
    );
}

export default function RatingSummary({
    rating,
    empty,
}: {
    rating: Rating;
    // What to say when nothing is published yet. Without it a member with no reviews shows nothing.
    empty?: string;
}) {
    const { t, locale } = useTranslation();
    if (rating.count === 0 || rating.average === null)
        return empty ? <span className="rating-summary">{empty}</span> : null;
    const average = new Intl.NumberFormat(locale, {
        minimumFractionDigits: 1,
        maximumFractionDigits: 1,
    }).format(rating.average);
    return (
        <span className="rating-summary">
            <Star size={16} aria-hidden="true" className="review-star-on" />
            <span className="sr-only">
                {t('Rated :rating out of 5', { rating: average })}
            </span>
            <strong aria-hidden="true">{average}</strong>
            <span>
                {rating.count === 1
                    ? t('1 review')
                    : t(':count reviews', { count: rating.count })}
            </span>
        </span>
    );
}

export function ReviewList({
    reviews,
    total,
}: {
    reviews: PublicReview[];
    // Every published review, of which only the newest are listed.
    total: number;
}) {
    const { t, locale } = useTranslation();
    const date = new Intl.DateTimeFormat(locale, {
        dateStyle: 'medium',
        timeZone: 'UTC',
    });
    return (
        <>
            <ul className="review-list">
                {reviews.map((review) => (
                    <li key={review.id}>
                        <p className="review-byline">
                            <Stars rating={review.rating} />
                            <span>
                                {review.author && (
                                    <>
                                        <bdi>{review.author}</bdi>
                                        {' · '}
                                    </>
                                )}
                                <time dateTime={review.created_at}>
                                    {date.format(new Date(review.created_at))}
                                </time>
                            </span>
                        </p>
                        {review.project_title && (
                            <h3 className="review-project" dir="auto">
                                {review.project_title}
                            </h3>
                        )}
                        <p className="review-text" dir="auto">
                            {review.body}
                        </p>
                    </li>
                ))}
            </ul>
            {total > reviews.length && (
                <p className="review-list-note">
                    {t('Showing the :shown most recent of :count reviews.', {
                        shown: reviews.length,
                        count: total,
                    })}
                </p>
            )}
        </>
    );
}

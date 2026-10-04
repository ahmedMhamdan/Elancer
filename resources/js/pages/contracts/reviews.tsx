import { useForm } from '@inertiajs/react';
import { Star } from 'lucide-react';
import InputError from '@/components/input-error';
import Button from '@/components/tailadmin/button';
import TextArea from '@/components/tailadmin/textarea';
import { useTranslation } from '@/hooks/use-translation';
import { OfferTime, type Contract } from '@/pages/offers/shared';

type Review = { rating: number; body: string };
export type ReviewState = {
    published: boolean;
    publishes_at: string;
    mine: Review | null;
    theirs: Review | null;
    theirs_submitted: boolean;
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

function Shown({ title, review }: { title: string; review: Review }) {
    return (
        <div className="market-stack">
            <div className="market-actions">
                <h3 className="!mb-0">{title}</h3>
                <Stars rating={review.rating} />
            </div>
            <p dir="auto" className="market-prose break-words">
                {review.body}
            </p>
        </div>
    );
}

export function Reviews({
    contract,
    reviews,
}: {
    contract: Contract;
    reviews: ReviewState;
}) {
    const { t, locale } = useTranslation();
    const form = useForm({
        rating: reviews.mine?.rating ?? 0,
        body: reviews.mine?.body ?? '',
    });
    const errors = form.errors as Record<string, string>;
    const counterpart = contract.is_client
        ? contract.agreement.freelancer_name
        : contract.agreement.client_name;
    const editable = !reviews.published || !reviews.mine;
    return (
        <section className="market-panel market-stack">
            <h2>{t('Reviews')}</h2>
            <p className="market-muted">
                {reviews.published
                    ? t('Reviews for this contract are published.')
                    : t(
                          'Reviews stay hidden until both of you have submitted one. Otherwise they publish on:',
                      )}{' '}
                {!reviews.published && (
                    <OfferTime value={reviews.publishes_at} />
                )}
            </p>
            {editable ? (
                <form
                    className="market-stack"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(`/contracts/${contract.id}/review`, {
                            preserveScroll: true,
                        });
                    }}
                >
                    <fieldset className="market-field">
                        <legend>
                            {t('Your rating of :name', { name: counterpart })}
                        </legend>
                        <div className="review-rating">
                            {[1, 2, 3, 4, 5].map((star) => (
                                <label key={star}>
                                    <input
                                        type="radio"
                                        name="rating"
                                        className="sr-only"
                                        value={star}
                                        checked={form.data.rating === star}
                                        onChange={() =>
                                            form.setData('rating', star)
                                        }
                                        required
                                    />
                                    <Star
                                        size={28}
                                        aria-hidden="true"
                                        className={
                                            star <= form.data.rating
                                                ? 'review-star-on'
                                                : ''
                                        }
                                    />
                                    <span className="sr-only">
                                        {t(':rating out of 5', {
                                            rating: star.toLocaleString(locale),
                                        })}
                                    </span>
                                </label>
                            ))}
                        </div>
                        <InputError message={errors.rating} />
                    </fieldset>
                    <div className="market-field">
                        <label htmlFor="review-body">{t('Your review')}</label>
                        <TextArea
                            id="review-body"
                            placeholder={t(
                                'Describe how the work and communication went.',
                            )}
                            value={form.data.body}
                            onChange={(value) => form.setData('body', value)}
                            required
                            minLength={20}
                            maxLength={3000}
                            rows={5}
                        />
                        <InputError message={errors.body ?? errors.review} />
                    </div>
                    <p className="market-muted">
                        {reviews.mine
                            ? t(
                                  'Your review is saved and hidden. You can edit it until reviews are published.',
                              )
                            : reviews.published
                              ? t(
                                    'Reviews are already published, so yours appears at once and cannot be edited.',
                                )
                              : t(
                                    'You can edit your review until reviews are published.',
                                )}
                    </p>
                    <div>
                        <Button type="submit" disabled={form.processing}>
                            {reviews.mine
                                ? t('Update review')
                                : t('Submit review')}
                        </Button>
                    </div>
                </form>
            ) : (
                reviews.mine && (
                    <Shown title={t('Your review')} review={reviews.mine} />
                )
            )}
            {reviews.theirs ? (
                <Shown
                    title={t('Review from :name', { name: counterpart })}
                    review={reviews.theirs}
                />
            ) : (
                <p className="market-muted">
                    {reviews.theirs_submitted
                        ? t(
                              ':name has submitted a review. It appears when reviews are published.',
                              { name: counterpart },
                          )
                        : t(':name has not submitted a review yet.', {
                              name: counterpart,
                          })}
                </p>
            )}
        </section>
    );
}

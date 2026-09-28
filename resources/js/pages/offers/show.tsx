import { Link, router, useForm } from '@inertiajs/react';
import Button from '@/components/tailadmin/button';
import TextArea from '@/components/tailadmin/textarea';
import InputError from '@/components/input-error';
import { useTranslation } from '@/hooks/use-translation';
import {
    AgreementLayout,
    AgreementTerms,
    OfferStatus,
    OfferTime,
    type Offer,
} from './shared';
export default function Show({
    offer,
    history,
}: {
    offer: Offer;
    history: Offer[];
}) {
    const { t } = useTranslation();
    const form = useForm({
        action: '',
        version: offer.version,
        reason: '',
        offer: '',
    });
    const act = (action: string, confirmation: string) => {
        if (window.confirm(confirmation)) {
            form.transform((data) => ({
                ...data,
                action,
                version: offer.version,
                reason: action === 'changes_requested' ? data.reason : null,
            }));
            form.patch(`/offers/${offer.id}`, { preserveScroll: true });
        }
    };
    const reasons: Record<string, string> = {
        contact_blocked: t('Contact was blocked.'),
        account_restricted: t('An account was restricted.'),
        hiring_unavailable: t('Hiring is no longer available for this offer.'),
    };
    return (
        <AgreementLayout title={t('Final offer')}>
            <h2 dir="auto">{offer.project.title}</h2>
            <div className="market-actions">
                <OfferStatus status={offer.status} />
                <Link href={`/proposals/${offer.proposal_id}`}>
                    {t('View proposal')}
                </Link>
                <Button variant="outline" onClick={() => router.reload()}>
                    {t('Refresh status')}
                </Button>
            </div>
            <p>
                {t('Expires at')}: <OfferTime value={offer.expires_at} />
            </p>
            {offer.closed_at && (
                <p>
                    {t('Closed at')}: <OfferTime value={offer.closed_at} />
                </p>
            )}
            <AgreementTerms terms={offer.terms} />
            {offer.reason && (
                <section className="market-panel">
                    <h2>{t('Response details')}</h2>
                    <p dir="auto" className="market-prose break-words">
                        {offer.status === 'restricted'
                            ? (reasons[offer.reason] ??
                              t(
                                  'Hiring is no longer available for this offer.',
                              ))
                            : offer.reason}
                    </p>
                </section>
            )}
            <InputError message={form.errors.offer} />
            {offer.contract_id && (
                <Link
                    className="market-primary-link"
                    href={`/contracts/${offer.contract_id}`}
                >
                    {t('Open contract')}
                </Link>
            )}
            {offer.status === 'pending' && (
                <section className="market-panel market-stack">
                    <h2>{t('Respond to offer')}</h2>
                    <p>
                        {t(
                            'Acceptance closes hiring and creates a contract awaiting sandbox funding.',
                        )}
                    </p>
                    {offer.is_client ? (
                        <div>
                            <Button
                                variant="danger-outline"
                                disabled={form.processing}
                                onClick={() =>
                                    act(
                                        'withdraw',
                                        t(
                                            'Withdraw this offer? It will no longer be available for acceptance.',
                                        ),
                                    )
                                }
                            >
                                {t('Withdraw offer')}
                            </Button>
                        </div>
                    ) : (
                        <>
                            <div className="market-actions">
                                <Button
                                    disabled={form.processing}
                                    onClick={() =>
                                        act(
                                            'accept',
                                            t(
                                                'Accept these exact terms and create the contract?',
                                            ),
                                        )
                                    }
                                >
                                    {t('Accept offer')}
                                </Button>
                                <Button
                                    variant="danger-outline"
                                    disabled={form.processing}
                                    onClick={() =>
                                        act(
                                            'decline',
                                            t(
                                                'Decline this offer? It will no longer be available for acceptance.',
                                            ),
                                        )
                                    }
                                >
                                    {t('Decline offer')}
                                </Button>
                            </div>
                            <div className="market-field">
                                <label htmlFor="offer-changes">
                                    {t('Requested changes')}
                                </label>
                                <TextArea
                                    id="offer-changes"
                                    rows={4}
                                    value={form.data.reason}
                                    onChange={(value) =>
                                        form.setData('reason', value)
                                    }
                                    maxLength={3000}
                                />
                                <InputError message={form.errors.reason} />
                            </div>
                            <p className="market-muted">
                                {t(
                                    'Requesting changes closes this offer. The client may send a replacement with a new 72-hour window.',
                                )}
                            </p>
                            <div>
                                <Button
                                    variant="outline"
                                    disabled={form.processing}
                                    onClick={() =>
                                        act(
                                            'changes_requested',
                                            t(
                                                'Close this offer and send your requested changes?',
                                            ),
                                        )
                                    }
                                >
                                    {t('Request changes')}
                                </Button>
                            </div>
                        </>
                    )}
                </section>
            )}
            {offer.is_client &&
                offer.status !== 'accepted' &&
                offer.status !== 'pending' && (
                    <Link href={`/proposals/${offer.proposal_id}/offer`}>
                        {t('Prepare replacement offer')}
                    </Link>
                )}
            <section className="market-panel market-stack">
                <h2>{t('Offer history')}</h2>
                {history.map((item) => (
                    <div key={item.id} className="market-actions">
                        <Link href={`/offers/${item.id}`}>
                            {t('Offer :number', { number: item.id })}
                        </Link>
                        <OfferStatus status={item.status} />
                        <OfferTime value={item.created_at} />
                    </div>
                ))}
            </section>
        </AgreementLayout>
    );
}

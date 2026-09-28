import { Link } from '@inertiajs/react';
import Pagination from '@/components/tailadmin/pagination';
import { useTranslation } from '@/hooks/use-translation';
import { Money, type Page } from '@/pages/discovery/shared';
import { AgreementLayout, OfferStatus, OfferTime, type Offer } from './shared';
export default function Index({ offers }: { offers: Page<Offer> }) {
    const { t } = useTranslation();
    return (
        <AgreementLayout title={t('Offers')}>
            {!offers.data.length && (
                <section className="market-panel">
                    <p>{t('No final offers yet.')}</p>
                    <p className="market-muted">
                        {t(
                            'Clients can send a final offer from a submitted proposal.',
                        )}
                    </p>
                </section>
            )}
            {offers.data.map((offer) => (
                <article key={offer.id} className="market-panel market-stack">
                    <h2>
                        <Link href={`/offers/${offer.id}`} dir="auto">
                            {offer.project.title}
                        </Link>
                    </h2>
                    <div className="market-actions">
                        <OfferStatus status={offer.status} />
                        <Money
                            min={offer.terms.amount}
                            max={offer.terms.amount}
                        />
                        <span>
                            {offer.is_client
                                ? t('Sent offer')
                                : t('Received offer')}
                        </span>
                    </div>
                    <p>
                        {t('Expires at')}:{' '}
                        <OfferTime value={offer.expires_at} />
                    </p>
                </article>
            ))}
            <Pagination data={offers} />
        </AgreementLayout>
    );
}

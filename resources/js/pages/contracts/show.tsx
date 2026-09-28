import { Link } from '@inertiajs/react';
import { useTranslation } from '@/hooks/use-translation';
import {
    AgreementLayout,
    AgreementTerms,
    OfferTime,
    type Contract,
} from '@/pages/offers/shared';
export default function Show({ contract }: { contract: Contract }) {
    const { t } = useTranslation();
    return (
        <AgreementLayout title={t('Contract agreement')}>
            <h2 dir="auto">{contract.agreement.project_title}</h2>
            <section className="market-panel market-stack">
                <h2>{t('Awaiting sandbox funding')}</h2>
                <p>
                    {t(
                        'The agreement is accepted. Funding is not available yet, and the delivery clock has not started.',
                    )}
                </p>
                <dl className="proposal-terms">
                    <div>
                        <dt>{t('Client')}</dt>
                        <dd dir="auto">{contract.agreement.client_name}</dd>
                    </div>
                    <div>
                        <dt>{t('Freelancer')}</dt>
                        <dd dir="auto">{contract.agreement.freelancer_name}</dd>
                    </div>
                    <div>
                        <dt>{t('Accepted at')}</dt>
                        <dd>
                            <OfferTime value={contract.agreement.accepted_at} />
                        </dd>
                    </div>
                </dl>
            </section>
            <AgreementTerms terms={contract.agreement} />
            <div className="market-actions">
                <Link
                    className="market-primary-link"
                    href={`/messages/${contract.conversation_id}`}
                >
                    {t('Open messages')}
                </Link>
                <Link href={`/offers/${contract.offer_id}`}>
                    {t('View accepted offer')}
                </Link>
                <Link href={`/jobs/${contract.project_id}`}>
                    {t('View project')}
                </Link>
            </div>
        </AgreementLayout>
    );
}

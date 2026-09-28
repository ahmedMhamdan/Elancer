import { Link } from '@inertiajs/react';
import Pagination from '@/components/tailadmin/pagination';
import { useTranslation } from '@/hooks/use-translation';
import { Money, type Page } from '@/pages/discovery/shared';
import { AgreementLayout, type Contract } from '@/pages/offers/shared';
export default function Index({ contracts }: { contracts: Page<Contract> }) {
    const { t } = useTranslation();
    return (
        <AgreementLayout title={t('Contracts')}>
            {!contracts.data.length && (
                <section className="market-panel">
                    <p>{t('No contracts yet.')}</p>
                    <p className="market-muted">
                        {t(
                            'A contract is created when the freelancer accepts a final offer.',
                        )}
                    </p>
                </section>
            )}
            {contracts.data.map((contract) => (
                <article
                    key={contract.id}
                    className="market-panel market-stack"
                >
                    <h2>
                        <Link href={`/contracts/${contract.id}`} dir="auto">
                            {contract.agreement.project_title}
                        </Link>
                    </h2>
                    <div className="market-actions">
                        <span>{t('Awaiting sandbox funding')}</span>
                        <Money
                            min={contract.agreement.amount}
                            max={contract.agreement.amount}
                        />
                    </div>
                </article>
            ))}
            <Pagination data={contracts} />
        </AgreementLayout>
    );
}

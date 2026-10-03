import { Head } from '@inertiajs/react';
import Pagination from '@/components/tailadmin/pagination';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import type { Page } from '@/pages/discovery/shared';
import { PaymentHistory, type Attempt } from './payment-history';
import '../../../css/elancer-marketplace.css';

export default function Payments({ attempts }: { attempts: Page<Attempt> }) {
    const { t } = useTranslation();
    return (
        <AppLayout
            breadcrumbs={[
                { title: t('Finance'), href: '/finance' },
                { title: t('Payment history'), href: '/finance/payments' },
            ]}
        >
            <Head title={t('Payment history')} />
            <main className="workspace-dashboard market-workspace market-stack">
                <div>
                    <div className="market-actions">
                        <h1 className="text-2xl font-semibold">
                            {t('Payment history')}
                        </h1>
                        <span className="proposal-status">
                            {t('Test mode')}
                        </span>
                    </div>
                    <p className="text-muted-foreground mt-2 text-sm">
                        {t(
                            'Every test payment attempt on your contracts, newest first.',
                        )}
                    </p>
                </div>
                <section
                    className="border-border bg-card overflow-hidden rounded-2xl border pt-5"
                    aria-label={t('Payment history')}
                >
                    <PaymentHistory attempts={attempts.data} />
                </section>
                <Pagination data={attempts} />
            </main>
        </AppLayout>
    );
}

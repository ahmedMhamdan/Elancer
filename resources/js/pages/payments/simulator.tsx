import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Button from '@/components/tailadmin/button';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import { Money } from '@/pages/discovery/shared';
import type { Payment } from '@/pages/offers/shared';
import '../../../css/elancer-marketplace.css';

// Offline stand-in for a provider's checkout page, used by automated tests.
export default function Simulator({
    attempt,
    open,
    contract,
}: {
    attempt: Payment;
    open: boolean;
    contract: { id: number; project_title: string | null };
}) {
    const { t } = useTranslation();
    const [busy, setBusy] = useState(false);
    const decide = (decision: string) => {
        setBusy(true);
        router.post(
            `/payments/${attempt.id}/simulator`,
            { decision },
            { onFinish: () => setBusy(false) },
        );
    };
    return (
        <AppLayout breadcrumbs={[{ title: t('Finance'), href: '/finance' }]}>
            <Head title={t('Offline test simulator')} />
            <main className="workspace-dashboard market-workspace market-stack">
                <h1 className="text-2xl font-semibold">
                    {t('Offline test simulator')}
                </h1>
                <section className="market-panel market-stack">
                    <p>
                        {t(
                            'This page stands in for a payment provider during testing. No real money moves.',
                        )}
                    </p>
                    <dl className="proposal-terms">
                        <div>
                            <dt>{t('Project')}</dt>
                            <dd dir="auto">{contract.project_title}</dd>
                        </div>
                        <div>
                            <dt>{t('Amount')}</dt>
                            <dd>
                                <Money
                                    min={attempt.amount}
                                    max={attempt.amount}
                                />
                            </dd>
                        </div>
                    </dl>
                    {open ? (
                        <div className="market-actions">
                            <Button
                                disabled={busy}
                                onClick={() => decide('approve')}
                            >
                                {t('Approve test payment')}
                            </Button>
                            <Button
                                variant="danger-outline"
                                disabled={busy}
                                onClick={() => decide('decline')}
                            >
                                {t('Decline test payment')}
                            </Button>
                        </div>
                    ) : (
                        <p>{t('This payment attempt is already closed.')}</p>
                    )}
                    <Link href={`/contracts/${contract.id}`}>
                        {t('Back to contract')}
                    </Link>
                </section>
            </main>
        </AppLayout>
    );
}

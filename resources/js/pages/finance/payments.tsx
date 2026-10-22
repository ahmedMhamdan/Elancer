// Payment history (plan 032 part 12): every test payment on the member's contracts in the Overview's rows, grouped by
// month, newest first, fifteen to a page.
import { usePage } from '@inertiajs/react';
import Pagination from '@/components/tailadmin/pagination';
import { BlurFade } from '@/components/ui/blur-fade';
import { useTranslation } from '@/hooks/use-translation';
import type { Page } from '@/pages/discovery/shared';
import { PaymentRows } from './payment-history';
import {
    FinanceLayout,
    NoPayments,
    ProvidersLine,
    type Attempt,
} from './shared';

export default function Payments({
    attempts,
    providers,
}: {
    attempts: Page<Attempt>;
    providers: string[];
}) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    return (
        <FinanceLayout tab="payments" count={attempts.total}>
            <BlurFade>
                <section
                    className="finance-card finance-payments"
                    aria-labelledby="finance-every"
                >
                    <div className="finance-card-head">
                        <h2 id="finance-every">
                            {t('Every payment on your contracts')}
                        </h2>
                        <span className="finance-muted">
                            {t('Newest first')}
                        </span>
                    </div>
                    {attempts.data.length ? (
                        <PaymentRows attempts={attempts.data} grouped />
                    ) : (
                        <NoPayments
                            client={auth.user.workspace_role === 'client'}
                        />
                    )}
                </section>
            </BlurFade>
            {attempts.last_page > 1 && <Pagination data={attempts} />}
            <BlurFade delay={0.05}>
                <ProvidersLine providers={providers} />
            </BlurFade>
        </FinanceLayout>
    );
}

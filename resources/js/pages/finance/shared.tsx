// Finance (plan 032 part 12, Ahmed's pick "A"): the heading with the Test mode label, Overview and Payment history as
// tabs (the hiring tabs' underline), the quiet line for the test providers and the empty state. Status colours as on
// My projects, Final offers and Contracts. Never a balance, earnings or payout.
import { Head, Link } from '@inertiajs/react';
import { CreditCard, FlaskConical } from 'lucide-react';
import { useMemo, type ReactNode } from 'react';
import HelpLink from '@/components/help-link';
import { NumberTicker } from '@/components/ui/number-ticker';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import {
    ProviderMark,
    useProviderName,
    type Payment,
} from '@/pages/offers/shared';
import '../../../css/elancer-marketplace.css';
import '../../../css/elancer-projects.css';
import '../../../css/elancer-finance.css';

export type Attempt = Payment & {
    contract_id: number;
    project_title: string | null;
    refunded_at: string | null;
};

export function FinanceLayout({
    tab,
    count,
    children,
}: {
    tab: 'overview' | 'payments';
    count: number;
    children: ReactNode;
}) {
    const { t } = useTranslation();
    const title = tab === 'overview' ? t('Finance') : t('Payment history');
    return (
        <AppLayout
            breadcrumbs={[
                { title: t('Finance'), href: '/finance' },
                ...(tab === 'payments'
                    ? [{ title, href: '/finance/payments' }]
                    : []),
            ]}
        >
            <Head title={title} />
            <main className="workspace-dashboard market-workspace market-stack finance-page">
                <div>
                    <div className="finance-heading">
                        <h1 className="text-2xl font-semibold">
                            {t('Finance')}
                        </h1>
                        <span className="finance-test-mode">
                            <FlaskConical aria-hidden="true" />
                            {t('Test mode')}
                        </span>
                    </div>
                    <p className="finance-lead">
                        {t(
                            'Agreed amounts and test payments on your contracts. No real money moves.',
                        )}
                    </p>
                    <HelpLink article="test-mode" className="mt-2" />
                </div>
                <nav className="hiring-nav" aria-label={t('Finance')}>
                    <Link
                        href="/finance"
                        aria-current={tab === 'overview' ? 'page' : undefined}
                    >
                        {t('Overview')}
                    </Link>
                    <Link
                        href="/finance/payments"
                        aria-current={tab === 'payments' ? 'page' : undefined}
                    >
                        {t('Payment history')}
                        {count > 0 && (
                            <small className="finance-tab-count">{count}</small>
                        )}
                    </Link>
                </nav>
                {children}
            </main>
        </AppLayout>
    );
}

/** A time in full, as "Oct 20, 2026, 6:40 PM", in the page's language (for a title or a receipt). */
export function useFullTime() {
    const { locale } = useTranslation();
    const format = useMemo(
        () =>
            new Intl.DateTimeFormat(locale, {
                dateStyle: 'medium',
                timeStyle: 'short',
            }),
        [locale],
    );
    return (value: string) => format.format(new Date(value));
}

/** An amount as "$1,080.00", left to right in both languages; counting once when asked (Magic UI Number Ticker). */
export function Amount({ value, count }: { value: string; count?: boolean }) {
    const { locale } = useTranslation();
    const figure = new Intl.NumberFormat(locale, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value));
    return (
        <bdi dir="ltr" className="finance-figure">
            {count ? (
                <>
                    <span className="sr-only">${figure}</span>
                    <span aria-hidden="true">
                        $
                        <NumberTicker value={Number(value)} decimalPlaces={2} />
                    </span>
                </>
            ) : (
                `$${figure}`
            )}
        </bdi>
    );
}

/** One quiet line: the test providers with their marks, and the one rule. */
export function ProvidersLine({ providers }: { providers: string[] }) {
    const { t } = useTranslation();
    const name = useProviderName();
    const cards = providers.some((p) => p === 'stripe' || p === 'moyasar');
    return (
        <section className="finance-card finance-providers">
            {providers.length > 0 && (
                <div className="finance-provider-list">
                    <span className="finance-muted">{t('Test providers')}</span>
                    {providers.map((provider) => (
                        <span key={provider} className="finance-provider">
                            <ProviderMark provider={provider} size="sm" />
                            {name(provider)}
                        </span>
                    ))}
                </div>
            )}
            <p className="finance-rule">
                {providers.length === 0
                    ? t(
                          'No payment provider is connected yet, so funding is not available.',
                      ) + ' '
                    : cards
                      ? t('Use the test cards shown on the funding page.') + ' '
                      : ''}
                {t(
                    'Elancer never holds or moves real money: no balance, payouts or withdrawals.',
                )}
            </p>
        </section>
    );
}

export function NoPayments({ client }: { client: boolean }) {
    const { t } = useTranslation();
    return (
        <div className="finance-empty">
            <span className="finance-empty-icon" aria-hidden="true">
                <CreditCard />
            </span>
            <strong>{t('No payments yet')}</strong>
            <p>
                {client
                    ? t('A payment appears here when you fund a contract.')
                    : t(
                          'A payment appears here when a client funds one of your contracts.',
                      )}
            </p>
        </div>
    );
}

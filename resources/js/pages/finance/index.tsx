// Adapted from local TailAdmin src/components/ecommerce/EcommerceMetrics.tsx and
// RecentOrders.tsx (MIT): metric blocks with icon tiles and a bordered table card.
// Demo trends, images and filters are removed; rows are the member's own contracts
// and payment attempts with Elancer tokens. See THIRD_PARTY_NOTICES.md.
import { Head, Link, usePage } from '@inertiajs/react';
import { CircleCheck, Clock } from 'lucide-react';
import Pagination from '@/components/tailadmin/pagination';
import {
    Table,
    TableBody,
    TableCell,
    TableHeader,
    TableRow,
    TableScroll,
} from '@/components/tailadmin/table';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import { Money, type Page } from '@/pages/discovery/shared';
import {
    ContractStatus,
    OfferTime,
    PaymentStatus,
    ProviderMark,
    useProviderHint,
    useProviderLabel,
    type Payment,
} from '@/pages/offers/shared';
import { PaymentHistory, cell, head, type Attempt } from './payment-history';
import '../../../css/elancer-marketplace.css';

type Totals = { count: number; total: string };
type Role = { awaiting: Totals; funded: Totals };
type Row = {
    id: number;
    status: string;
    funded_at: string | null;
    delivery_due_at: string | null;
    project_title: string | null;
    amount: string;
    counterpart: string | null;
    is_client: boolean;
    payment: Payment | null;
};

export default function Finance({
    summary,
    providers,
    contracts,
    attempts,
}: {
    summary: { client: Role; freelancer: Role };
    providers: string[];
    contracts: Page<Row>;
    attempts: Attempt[];
}) {
    const { t, locale } = useTranslation();
    const { auth } = usePage().props;
    const providerLabel = useProviderLabel();
    const providerHint = useProviderHint();
    const groups = [
        {
            key: 'client',
            role: summary.client,
            title: t('As a client'),
            awaiting: t('Awaiting your funding'),
            funded: t('Funded by you'),
        },
        {
            key: 'freelancer',
            role: summary.freelancer,
            title: t('As a freelancer'),
            awaiting: t('Awaiting client funding'),
            funded: t('Funded for your work'),
        },
    ];
    // The current workspace's totals always show; the other role only when it has contracts.
    const workspace = auth.user.workspace_role ?? 'freelancer';
    const shown = groups
        .filter(
            ({ key, role }) =>
                key === workspace ||
                role.awaiting.count + role.funded.count > 0,
        )
        .sort(
            (a, b) => Number(b.key === workspace) - Number(a.key === workspace),
        );
    return (
        <AppLayout breadcrumbs={[{ title: t('Finance'), href: '/finance' }]}>
            <Head title={t('Finance')} />
            <main className="workspace-dashboard market-workspace market-stack">
                <div>
                    <div className="market-actions">
                        <h1 className="text-2xl font-semibold">
                            {t('Finance')}
                        </h1>
                        <span className="proposal-status">
                            {t('Test mode')}
                        </span>
                    </div>
                    <p className="text-muted-foreground mt-2 text-sm">
                        {t(
                            'Agreed contract amounts and test payments. Elancer does not hold or move real money, so there is no balance to withdraw.',
                        )}
                    </p>
                </div>

                {shown.map(({ role, title, awaiting, funded }) => (
                    <section key={title} aria-label={title}>
                        <h2 className="text-muted-foreground mb-3 text-sm font-medium">
                            {title}
                        </h2>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            {[
                                {
                                    label: awaiting,
                                    totals: role.awaiting,
                                    Icon: Clock,
                                },
                                {
                                    label: funded,
                                    totals: role.funded,
                                    Icon: CircleCheck,
                                },
                            ].map(({ label, totals, Icon }) => (
                                <div
                                    key={label}
                                    className="border-border bg-card rounded-2xl border p-5"
                                >
                                    <div className="bg-muted text-primary flex size-12 items-center justify-center rounded-xl">
                                        <Icon
                                            className="size-6"
                                            aria-hidden="true"
                                        />
                                    </div>
                                    <div className="mt-5 flex items-end justify-between gap-3">
                                        <div>
                                            <span className="text-muted-foreground text-sm">
                                                {label}
                                            </span>
                                            <p className="mt-2 text-2xl font-semibold tabular-nums">
                                                <Money
                                                    min={totals.total}
                                                    max={totals.total}
                                                />
                                            </p>
                                        </div>
                                        <span className="proposal-status">
                                            {totals.count === 1
                                                ? t('1 contract')
                                                : t(':count contracts', {
                                                      count: totals.count.toLocaleString(
                                                          locale,
                                                      ),
                                                  })}
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>
                ))}

                <section className="border-border bg-card overflow-hidden rounded-2xl border">
                    <h2 className="!mb-0 px-4 pt-5 pb-3">
                        {t('Contracts and funding')}
                    </h2>
                    {contracts.data.length ? (
                        <TableScroll label={t('Contracts and funding')}>
                            <Table className="w-full">
                                <TableHeader className="border-border border-y">
                                    <TableRow>
                                        <TableCell isHeader className={head}>
                                            {t('Project')}
                                        </TableCell>
                                        <TableCell isHeader className={head}>
                                            {t('Your role')}
                                        </TableCell>
                                        <TableCell isHeader className={head}>
                                            {t('Amount')}
                                        </TableCell>
                                        <TableCell isHeader className={head}>
                                            {t('Status')}
                                        </TableCell>
                                        <TableCell isHeader className={head}>
                                            {t('First delivery due')}
                                        </TableCell>
                                        <TableCell
                                            isHeader
                                            className={`${head} w-px text-end`}
                                        >
                                            {t('Actions')}
                                        </TableCell>
                                    </TableRow>
                                </TableHeader>
                                <TableBody className="divide-border divide-y">
                                    {contracts.data.map((row) => (
                                        <TableRow key={row.id}>
                                            <TableCell
                                                className={`${cell} min-w-48`}
                                            >
                                                <span
                                                    dir="auto"
                                                    className="block font-medium wrap-anywhere"
                                                >
                                                    {row.project_title}
                                                </span>
                                                <span
                                                    dir="auto"
                                                    className="text-muted-foreground text-xs"
                                                >
                                                    {row.counterpart}
                                                </span>
                                            </TableCell>
                                            <TableCell className={cell}>
                                                {row.is_client
                                                    ? t('Client')
                                                    : t('Freelancer')}
                                            </TableCell>
                                            <TableCell
                                                className={`${cell} whitespace-nowrap`}
                                            >
                                                <Money
                                                    min={row.amount}
                                                    max={row.amount}
                                                />
                                            </TableCell>
                                            <TableCell className={cell}>
                                                {row.payment?.status ===
                                                'pending' ? (
                                                    <PaymentStatus status="pending" />
                                                ) : (
                                                    <ContractStatus
                                                        status={row.status}
                                                    />
                                                )}
                                            </TableCell>
                                            <TableCell
                                                className={`${cell} whitespace-nowrap`}
                                            >
                                                {row.delivery_due_at ? (
                                                    <OfferTime
                                                        value={
                                                            row.delivery_due_at
                                                        }
                                                    />
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        {t(
                                                            'Starts after funding',
                                                        )}
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell
                                                className={`${cell} w-px text-end whitespace-nowrap`}
                                            >
                                                <Link
                                                    href={`/contracts/${row.id}`}
                                                    className="text-primary inline-flex min-h-11 items-center font-medium underline-offset-4 hover:underline"
                                                    aria-label={`${
                                                        row.is_client &&
                                                        row.status ===
                                                            'awaiting_payment'
                                                            ? t('Fund contract')
                                                            : t('Open contract')
                                                    }: ${row.project_title ?? ''}`}
                                                >
                                                    {row.is_client &&
                                                    row.status ===
                                                        'awaiting_payment'
                                                        ? t('Fund contract')
                                                        : t('Open contract')}
                                                </Link>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </TableScroll>
                    ) : (
                        <div className="px-4 pb-5">
                            <p>{t('No contracts yet.')}</p>
                            <p className="market-muted">
                                {t(
                                    'A contract is created when the freelancer accepts a final offer.',
                                )}
                            </p>
                        </div>
                    )}
                </section>
                <Pagination data={contracts} />

                <section className="border-border bg-card overflow-hidden rounded-2xl border">
                    <div className="flex flex-wrap items-center justify-between gap-3 px-4 pt-5 pb-3">
                        <h2 className="!mb-0">{t('Payment history')}</h2>
                        <Link
                            href="/finance/payments"
                            className="text-primary inline-flex min-h-11 items-center text-sm font-medium underline-offset-4 hover:underline"
                        >
                            {t('View all payments')}
                        </Link>
                    </div>
                    <PaymentHistory attempts={attempts} />
                </section>

                <section className="market-panel market-stack">
                    <h2>{t('Payment providers')}</h2>
                    {providers.length ? (
                        <ul className="provider-options">
                            {providers.map((provider) => (
                                <li
                                    key={provider}
                                    className="provider-option provider-option-static"
                                >
                                    <ProviderMark provider={provider} />
                                    <span className="min-w-0 flex-1">
                                        <span className="block font-medium">
                                            {providerLabel(provider)}
                                        </span>
                                        <span className="market-muted block text-sm">
                                            {providerHint(provider)}
                                        </span>
                                    </span>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p>
                            {t(
                                'No payment provider is connected yet, so funding is not available.',
                            )}
                        </p>
                    )}
                    <h3>{t('Not available yet')}</h3>
                    <ul className="market-muted list-disc space-y-2 ps-6">
                        <li>
                            {t(
                                'Real payments, payouts and withdrawals are outside this test marketplace.',
                            )}
                        </li>
                    </ul>
                </section>
            </main>
        </AppLayout>
    );
}

// Finance overview (plan 032 part 12, Ahmed's pick "A"): one card per role with the agreed total and a bar split into
// completed, funded with work under way and awaiting funding (after Tremor's Category Bar,
// https://tremor.so/docs/visualizations/category-bar; written for Elancer, no code copied, as TailAdmin has no split
// bar), refunds on a line under it; what waits for funding with its one action; the five latest payments, each
// opening its receipt; one quiet line for the test providers. Contracts lists every contract, so Finance shows only
// the money. The figures count once per visit (Magic UI Number Ticker); sections fade in (Magic UI BlurFade).
import { Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import type { CSSProperties } from 'react';
import { BlurFade } from '@/components/ui/blur-fade';
import { InteractiveHoverButton } from '@/components/ui/interactive-hover-button';
import { useRelativeTime } from '@/hooks/use-relative-time';
import { useTranslation } from '@/hooks/use-translation';
import { useProviderName, type Payment } from '@/pages/offers/shared';
import { PaymentRows } from './payment-history';
import {
    Amount,
    FinanceLayout,
    NoPayments,
    ProvidersLine,
    useFullTime,
    type Attempt,
} from './shared';

type Totals = { count: number; total: string };
type Role = {
    awaiting: Totals;
    running: Totals;
    completed: Totals;
    refunded: Totals;
    funded: Totals;
};
type Waiting = {
    id: number;
    conversation_id: number;
    created_at: string;
    project_title: string | null;
    amount: string;
    counterpart: string | null;
    is_client: boolean;
    payment: Payment | null;
};
const cents = (value: string) => Math.round(Number(value) * 100);

function useContracts() {
    const { t, locale } = useTranslation();
    return (count: number) =>
        count === 1
            ? t('1 contract')
            : t(':count contracts', { count: count.toLocaleString(locale) });
}

function Summary({
    role,
    totals,
    heading,
}: {
    role: 'client' | 'freelancer';
    totals: Role;
    heading: string | null;
}) {
    const { t } = useTranslation();
    const contracts = useContracts();
    const parts: [keyof Role, string, string][] = [
        ['completed', t('Completed'), 'open'],
        ['running', t('Funded, work under way'), 'hired'],
        [
            'awaiting',
            role === 'client'
                ? t('Awaiting your funding')
                : t('Awaiting client funding'),
            'wait',
        ],
    ];
    const agreedCents = parts.reduce(
        (sum, [key]) => sum + cents(totals[key].total),
        0,
    );
    const agreedCount = parts.reduce(
        (sum, [key]) => sum + totals[key].count,
        0,
    );
    const label = t('Agreed on your contracts');
    return (
        <section aria-label={heading ?? label}>
            {heading && <h2 className="finance-role">{heading}</h2>}
            <div className="finance-card finance-summary">
                {agreedCount === 0 && totals.refunded.count === 0 ? (
                    <div className="finance-empty">
                        <strong>{t('No contracts yet.')}</strong>
                        <p>
                            {t(
                                'A contract is created when the freelancer accepts a final offer.',
                            )}
                        </p>
                        <Link href="/offers" className="my-project-link">
                            {t('Go to Final offers')}
                        </Link>
                    </div>
                ) : (
                    <>
                        <div className="finance-summary-top">
                            <div>
                                <span className="finance-muted">{label}</span>
                                <strong className="finance-total">
                                    <Amount
                                        value={(agreedCents / 100).toFixed(2)}
                                        count
                                    />
                                </strong>
                            </div>
                            <span className="finance-chip">
                                {contracts(agreedCount)}
                            </span>
                        </div>
                        <div
                            className="finance-bar"
                            role="img"
                            aria-label={parts
                                .map(
                                    ([key, name]) =>
                                        `${name} $${totals[key].total}`,
                                )
                                .join(', ')}
                        >
                            {parts
                                .filter(([key]) => totals[key].count > 0)
                                .map(([key, , state]) => (
                                    <i
                                        key={key}
                                        data-state={state}
                                        style={
                                            {
                                                flexGrow: Math.max(
                                                    1,
                                                    cents(totals[key].total),
                                                ),
                                            } as CSSProperties
                                        }
                                    />
                                ))}
                        </div>
                        <ul className="finance-legend">
                            {parts.map(([key, name, state]) => (
                                <li key={key}>
                                    <i data-state={state} aria-hidden="true" />
                                    <span className="finance-muted">
                                        {name}
                                    </span>
                                    <strong>
                                        <Amount
                                            value={totals[key].total}
                                            count
                                        />
                                    </strong>
                                    <small>
                                        {contracts(totals[key].count)}
                                    </small>
                                </li>
                            ))}
                        </ul>
                        {totals.refunded.count > 0 && (
                            <p className="finance-refund">
                                <span>
                                    {t('Refunded after a cancellation:')}{' '}
                                    <strong>
                                        <Amount value={totals.refunded.total} />
                                    </strong>
                                </span>{' '}
                                <span>
                                    {t(':contracts, not counted above.', {
                                        contracts: contracts(
                                            totals.refunded.count,
                                        ),
                                    })}
                                </span>
                            </p>
                        )}
                    </>
                )}
            </div>
        </section>
    );
}

function AwaitingCard({ row }: { row: Waiting }) {
    const { t } = useTranslation();
    const relative = useRelativeTime();
    const name = useProviderName();
    const full = useFullTime();
    const payment = row.payment;
    return (
        <article className="finance-card finance-waiting">
            <div className="finance-waiting-main">
                <div className="finance-waiting-top">
                    <span className="my-project-status" data-state="wait">
                        {row.is_client
                            ? t('Awaiting your funding')
                            : t('Awaiting client funding')}
                    </span>
                    <time
                        dateTime={row.created_at}
                        title={full(row.created_at)}
                    >
                        {t('Offer accepted :time', {
                            time: relative(row.created_at),
                        })}
                    </time>
                </div>
                <h3 dir="auto">
                    <Link href={`/contracts/${row.id}`}>
                        {row.project_title ?? t('Contract')}
                    </Link>
                </h3>
                <p className="finance-muted">
                    <bdi>{row.counterpart}</bdi>
                </p>
                {row.is_client && payment?.status === 'failed' && (
                    <p className="finance-late">
                        {t('Last try not completed :time', {
                            time: relative(payment.created_at),
                        })}{' '}
                        · {name(payment.provider)}
                    </p>
                )}
                {row.is_client && payment?.status === 'pending' && (
                    <p className="finance-muted">
                        {t('A payment is being checked')} ·{' '}
                        {name(payment.provider)}
                    </p>
                )}
                {!row.is_client && (
                    <p className="finance-muted">
                        {t('Work starts once the client funds it.')}
                    </p>
                )}
            </div>
            <div className="finance-waiting-side">
                <span className="finance-waiting-amount">
                    <Amount value={row.amount} />
                </span>
                {row.is_client ? (
                    <InteractiveHoverButton
                        href={`/contracts/${row.id}?tab=payments`}
                    >
                        {t('Fund contract')}
                    </InteractiveHoverButton>
                ) : (
                    <Link
                        href={`/messages/${row.conversation_id}`}
                        className="my-project-link"
                    >
                        {t('Send a message')}
                    </Link>
                )}
            </div>
        </article>
    );
}

export default function Finance({
    summary,
    providers,
    awaiting,
    awaiting_count,
    attempts,
    payments_count,
}: {
    summary: { client: Role; freelancer: Role };
    providers: string[];
    awaiting: Waiting[];
    awaiting_count: number;
    attempts: Attempt[];
    payments_count: number;
}) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const workspace: 'client' | 'freelancer' =
        auth.user.workspace_role === 'client' ? 'client' : 'freelancer';
    const used = (role: Role) =>
        role.awaiting.count +
            role.running.count +
            role.completed.count +
            role.refunded.count >
        0;
    // The current workspace's money always shows; the other role's only when it has contracts.
    const roles = (['client', 'freelancer'] as const)
        .filter((role) => role === workspace || used(summary[role]))
        .sort((a, b) => Number(b === workspace) - Number(a === workspace));
    const titles = {
        client: t('As a client'),
        freelancer: t('As a freelancer'),
    };
    // From the full counts, not the cards shown: ten client cards may sit above freelancer contracts left out.
    const toFund = summary.client.awaiting.count;
    const toWait = summary.freelancer.awaiting.count;
    const waitingTitle =
        toWait === 0
            ? t('To fund')
            : toFund === 0
              ? t('Waiting for funding')
              : t('Awaiting funding');
    return (
        <FinanceLayout tab="overview" count={payments_count}>
            {roles.map((role, index) => (
                <BlurFade key={role} delay={index * 0.05}>
                    <Summary
                        role={role}
                        totals={summary[role]}
                        heading={roles.length > 1 ? titles[role] : null}
                    />
                </BlurFade>
            ))}
            {awaiting.length > 0 && (
                <BlurFade delay={0.1}>
                    <section aria-labelledby="finance-awaiting">
                        <h2 id="finance-awaiting" className="finance-section">
                            {waitingTitle}
                            <small>{awaiting_count}</small>
                        </h2>
                        <div className="finance-waiting-list">
                            {awaiting.map((row) => (
                                <AwaitingCard key={row.id} row={row} />
                            ))}
                        </div>
                        {awaiting_count > awaiting.length && (
                            <Link
                                href="/contracts"
                                className="my-project-link finance-open finance-more"
                            >
                                {t('See all :count on Contracts', {
                                    count: awaiting_count,
                                })}
                                <ArrowRight
                                    className="size-4"
                                    aria-hidden="true"
                                />
                            </Link>
                        )}
                    </section>
                </BlurFade>
            )}
            <BlurFade delay={0.15}>
                <section
                    className="finance-card finance-payments"
                    aria-labelledby="finance-recent"
                >
                    <div className="finance-card-head">
                        <h2 id="finance-recent">{t('Recent payments')}</h2>
                        {payments_count > 0 && (
                            <Link
                                href="/finance/payments"
                                className="my-project-link finance-open"
                            >
                                {t('All payments')}
                                <ArrowRight
                                    className="size-4"
                                    aria-hidden="true"
                                />
                            </Link>
                        )}
                    </div>
                    {attempts.length ? (
                        <PaymentRows attempts={attempts} />
                    ) : (
                        <NoPayments client={workspace === 'client'} />
                    )}
                </section>
            </BlurFade>
            <BlurFade delay={0.2}>
                <ProvidersLine providers={providers} />
            </BlurFade>
        </FinanceLayout>
    );
}

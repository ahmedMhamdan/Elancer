// Payment rows (plan 032 part 12, Ahmed's pick "A"): one row per test payment with the provider's mark, the project,
// the provider and reference, the status as the site's small square, one time in words and the amount. A row opens
// its receipt in place (amount, provider, reference with Copy, started, verified or refunded, the reason it did not
// complete, a test-payment stamp and the contract), sliding open as Motion Primitives' Disclosure does
// (https://motion-primitives.com/docs/disclosure); written for Elancer with a CSS grid-rows transition, no code copied.
import { Link } from '@inertiajs/react';
import { ArrowRight, ChevronDown, FlaskConical } from 'lucide-react';
import { useState } from 'react';
import { useRelativeTime } from '@/hooks/use-relative-time';
import { useTranslation } from '@/hooks/use-translation';
import {
    ProviderMark,
    useProviderLabel,
    useProviderName,
    usePaymentReason,
    usePaymentStatusLabel,
} from '@/pages/offers/shared';
import { Amount, useFullTime, type Attempt } from './shared';

// The status as the site's small square: green verified, red not completed, amber while checked, grey otherwise.
const STATE: Record<string, string> = {
    succeeded: 'open',
    failed: 'hidden',
    pending: 'closed',
    unapplied: 'closed',
    cancelled: 'draft',
    refunded: 'draft',
};

function CopyReference({ value }: { value: string }) {
    const { t } = useTranslation();
    const [copied, setCopied] = useState(false);
    return (
        <button
            type="button"
            className="finance-copy"
            aria-label={copied ? t('Copied') : t('Copy the reference')}
            onClick={() => {
                void navigator.clipboard
                    ?.writeText(value)
                    .then(() => setCopied(true));
            }}
        >
            {copied ? t('Copied') : t('Copy')}
        </button>
    );
}

function Receipt({ attempt }: { attempt: Attempt }) {
    const { t } = useTranslation();
    const full = useFullTime();
    const provider = useProviderLabel();
    const status = usePaymentStatusLabel();
    const reason = usePaymentReason();
    const a = attempt;
    const why = reason(a.failure_reason);
    return (
        <div className="finance-receipt-body">
            <dl className="finance-receipt-list">
                <dt>{t('Amount')}</dt>
                <dd>
                    <Amount value={a.amount} />
                </dd>
                <dt>{t('Payment provider')}</dt>
                <dd>{provider(a.provider)}</dd>
                <dt>{t('Reference')}</dt>
                <dd>
                    <bdi className="finance-reference">{a.reference}</bdi>
                    <CopyReference value={a.reference} />
                </dd>
                <dt>{t('Started')}</dt>
                <dd>{full(a.created_at)}</dd>
                {a.verified_at && (
                    <>
                        <dt>{t('Verified')}</dt>
                        <dd>{full(a.verified_at)}</dd>
                    </>
                )}
                {a.status === 'refunded' && (
                    <>
                        <dt>{t('Refunded')}</dt>
                        <dd>
                            {a.refunded_at
                                ? t(':time, after the cancellation', {
                                      time: full(a.refunded_at),
                                  })
                                : t('After the cancellation')}
                        </dd>
                    </>
                )}
                {!['succeeded', 'refunded'].includes(a.status) && (
                    <>
                        <dt>{t('Result')}</dt>
                        <dd
                            className={
                                a.status === 'failed'
                                    ? 'finance-late'
                                    : undefined
                            }
                        >
                            {status(a.status)}
                            {why ? `: ${why}` : ''}
                        </dd>
                    </>
                )}
            </dl>
            <p className="finance-stamp">
                <FlaskConical aria-hidden="true" />
                {t('Test payment: no real money moved.')}
            </p>
            <div className="finance-receipt-foot">
                <Link
                    href={`/contracts/${a.contract_id}?tab=payments`}
                    className="my-project-link finance-open"
                >
                    {t('Open contract')}
                    <ArrowRight className="size-4" aria-hidden="true" />
                </Link>
            </div>
        </div>
    );
}

export function PaymentRows({
    attempts,
    grouped = false,
}: {
    attempts: Attempt[];
    grouped?: boolean;
}) {
    const { t, locale } = useTranslation();
    const relative = useRelativeTime();
    const full = useFullTime();
    const name = useProviderName();
    const status = usePaymentStatusLabel();
    const [open, setOpen] = useState<Record<number, boolean>>({});
    const month = new Intl.DateTimeFormat(locale, {
        month: 'long',
        year: 'numeric',
    });
    let last = '';
    return (
        <ul className="finance-payments-list">
            {attempts.flatMap((a) => {
                const items = [];
                const heading = month.format(new Date(a.created_at));
                if (grouped && heading !== last)
                    items.push(
                        <li key={`m-${a.id}`} className="finance-month">
                            {heading}
                        </li>,
                    );
                last = heading;
                const isOpen = !!open[a.id];
                items.push(
                    <li key={a.id} className="finance-payment">
                        <button
                            type="button"
                            className="finance-payment-row"
                            aria-expanded={isOpen}
                            aria-controls={`receipt-${a.id}`}
                            onClick={() =>
                                setOpen((now) => ({
                                    ...now,
                                    [a.id]: !now[a.id],
                                }))
                            }
                        >
                            <ProviderMark provider={a.provider} />
                            <span className="finance-payment-main">
                                <span
                                    className="finance-payment-title"
                                    dir="auto"
                                >
                                    {a.project_title ?? t('Contract')}
                                </span>
                                <small>
                                    {name(a.provider)} ·{' '}
                                    <bdi className="finance-reference">
                                        {a.reference}
                                    </bdi>
                                </small>
                            </span>
                            <span className="finance-payment-line">
                                <span
                                    className="my-project-status"
                                    data-state={STATE[a.status] ?? 'draft'}
                                >
                                    {status(a.status)}
                                </span>
                                <time
                                    dateTime={a.created_at}
                                    title={full(a.created_at)}
                                >
                                    {relative(a.created_at)}
                                </time>
                            </span>
                            <span
                                className={
                                    a.status === 'succeeded'
                                        ? 'finance-payment-amount'
                                        : 'finance-payment-amount finance-muted'
                                }
                            >
                                <Amount value={a.amount} />
                            </span>
                            <ChevronDown
                                className="finance-chevron"
                                aria-hidden="true"
                            />
                        </button>
                        <div
                            id={`receipt-${a.id}`}
                            className="finance-receipt"
                            data-open={isOpen}
                            inert={!isOpen}
                        >
                            <div>
                                <Receipt attempt={a} />
                            </div>
                        </div>
                    </li>,
                );
                return items;
            })}
        </ul>
    );
}

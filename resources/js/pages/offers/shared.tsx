// Cards/forms reuse local TailAdmin common/ComponentCard.tsx and form/form-elements/DefaultInputs.tsx.
// Existing adaptations retain Elancer tokens, keyboard controls and RTL. See THIRD_PARTY_NOTICES.md.
import { Head, Link, usePage } from '@inertiajs/react';
import { CircleCheck, FlaskConical } from 'lucide-react';
import type { ComponentType, ReactNode, SVGProps } from 'react';
import { MoyasarLogo, StripeLogo } from '@/components/provider-logos';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/hooks/use-translation';
import { Money } from '@/pages/discovery/shared';
import '../../../css/elancer-marketplace.css';

export type Terms = {
    scope: string;
    deliverables: string[];
    amount: string;
    currency: string;
    duration_days: number;
    revision_rounds: number;
};
// The other side of an offer as the viewer met them (plan 032 part 7).
export type Counterpart = {
    name: string | null;
    avatar: string | null;
    company: string | null;
};
// Whose move it is on an offer: the viewer's, the other side's, or nobody's once it is settled.
export type Turn = 'yours' | 'theirs' | null;
export type OfferRow = {
    id: number;
    proposal_id: number;
    project: { id: number; title: string };
    amount: string;
    status: string;
    expires_at: string;
    closed_at: string | null;
    created_at: string;
    is_client: boolean;
    counterpart: Counterpart;
    contract: { id: number; status: string } | null;
    latest: boolean;
    turn: Turn;
};
export type Offer = {
    id: number;
    proposal_id: number;
    project: { id: number; title: string };
    terms: Terms;
    status: string;
    version: number;
    expires_at: string;
    closed_at: string | null;
    reason: string | null;
    created_at: string;
    is_client: boolean;
    contract_id: number | null;
    contract: {
        id: number;
        status: string;
        delivery_due_at: string | null;
    } | null;
    counterpart: Counterpart;
    latest: boolean;
    turn: Turn;
    conversation_id: number | null;
    can_replace: boolean;
};
export type OfferState = 'open' | 'closed' | 'hired' | 'draft' | 'hidden';
export type Contract = {
    id: number;
    project_id: number;
    offer_id: number;
    conversation_id: number;
    status: string;
    created_at: string;
    funded_at: string | null;
    delivery_due_at: string | null;
    completed_at: string | null;
    cancelled_at: string | null;
    revisions_used: number;
    revision_rounds: number;
    revision_due_at: string | null;
    overdue: boolean;
    revision_overdue: boolean;
    is_client: boolean;
    agreement: Terms & {
        project_title: string;
        client_name: string;
        freelancer_name: string;
        accepted_at: string;
    };
};
export type Payment = {
    id: number;
    reference: string;
    provider: string;
    status: string;
    failure_reason: string | null;
    amount: string;
    currency: string;
    created_at: string;
    verified_at: string | null;
};

export function contractStatusLabel(
    status: string,
    t: (text: string) => string,
): string {
    const labels: Record<string, string> = {
        awaiting_payment: t('Awaiting funding'),
        active: t('Funded and active'),
        submitted: t('Delivered, awaiting review'),
        revision_requested: t('Revision requested'),
        completed: t('Completed'),
        cancellation_pending: t('Cancellation pending'),
        cancelled: t('Cancelled'),
    };
    return labels[status] ?? status;
}
export function ContractStatus({ status }: { status: string }) {
    const { t } = useTranslation();
    return (
        <span
            className={
                ['awaiting_payment', 'cancelled'].includes(status)
                    ? 'proposal-status'
                    : 'proposal-status proposal-status-submitted'
            }
        >
            {contractStatusLabel(status, t)}
        </span>
    );
}
export function usePaymentStatusLabel() {
    const { t } = useTranslation();
    const labels: Record<string, string> = {
        pending: t('Checking payment'),
        succeeded: t('Verified'),
        failed: t('Not completed'),
        cancelled: t('Cancelled'),
        unapplied: t('Verified, needs review'),
        refunded: t('Refunded'),
    };
    return (status: string) => labels[status] ?? status;
}
export function PaymentStatus({ status }: { status: string }) {
    const label = usePaymentStatusLabel();
    return (
        <span
            className={
                status === 'succeeded'
                    ? 'proposal-status proposal-status-submitted'
                    : 'proposal-status'
            }
        >
            {label(status)}
        </span>
    );
}
export function useProviderLabel() {
    const { t } = useTranslation();
    const labels: Record<string, string> = {
        stripe: t('Stripe (test mode)'),
        moyasar: t('Moyasar (test mode)'),
        simulator: t('Offline test simulator'),
    };
    return (provider: string) => labels[provider] ?? provider;
}
export function useProviderHint() {
    const { t } = useTranslation();
    const hints: Record<string, string> = {
        stripe: t('Pay by card on Stripe’s secure page.'),
        moyasar: t('mada, cards and Apple Pay on Moyasar’s secure page.'),
        simulator: t('Stand-in used for automated testing.'),
    };
    return (provider: string) => hints[provider] ?? '';
}
/** The provider's short name, as in "Stripe · 7C2E91A4" (plan 032 part 12). */
export function useProviderName() {
    const { t } = useTranslation();
    const names: Record<string, string> = {
        stripe: 'Stripe',
        moyasar: t('Moyasar'),
        simulator: t('Offline test simulator'),
    };
    return (provider: string) => names[provider] ?? provider;
}
// Each provider's own mark, white on its brand colour (plan 032 part 12, Ahmed: "Real logos"): Stripe's blurple,
// Moyasar's near-black; the offline simulator keeps a flask on grey. Logo sources: components/provider-logos.tsx.
const MARKS: Record<
    string,
    { color: string; Logo: ComponentType<SVGProps<SVGSVGElement>> }
> = {
    stripe: { color: '#635bff', Logo: StripeLogo },
    moyasar: { color: '#191502', Logo: MoyasarLogo },
    simulator: { color: '#626f60', Logo: FlaskConical },
};
export function ProviderMark({
    provider,
    size = 'md',
}: {
    provider: string;
    size?: 'sm' | 'md';
}) {
    const { color, Logo } = MARKS[provider] ?? MARKS.simulator;
    return (
        <span
            className={
                size === 'sm'
                    ? 'provider-mark provider-mark-sm'
                    : 'provider-mark'
            }
            data-provider={provider}
            style={{ backgroundColor: color }}
            aria-hidden="true"
        >
            <Logo className="provider-mark-logo" />
        </span>
    );
}
export function ProviderOptions({
    providers,
    value,
    onChange,
}: {
    providers: string[];
    value: string;
    onChange: (provider: string) => void;
}) {
    const { t } = useTranslation();
    const label = useProviderLabel();
    const hint = useProviderHint();
    return (
        <fieldset>
            <legend className="market-muted mb-3">
                {t('Payment provider')}
            </legend>
            <div className="provider-options">
                {providers.map((provider) => (
                    <label key={provider} className="provider-option">
                        <input
                            type="radio"
                            name="provider"
                            className="sr-only"
                            value={provider}
                            checked={value === provider}
                            onChange={() => onChange(provider)}
                        />
                        <ProviderMark provider={provider} />
                        <span className="min-w-0 flex-1">
                            <span className="block font-medium">
                                {label(provider)}
                            </span>
                            <span className="market-muted block text-sm">
                                {hint(provider)}
                            </span>
                        </span>
                        <CircleCheck
                            className="provider-option-check size-5"
                            aria-hidden="true"
                        />
                    </label>
                ))}
            </div>
        </fieldset>
    );
}
export function usePaymentReason() {
    const { t } = useTranslation();
    const reasons: Record<string, string> = {
        declined: t('The provider declined the payment.'),
        cancelled: t('The payment was cancelled before completion.'),
        mismatch: t(
            'The provider reported details that do not match this contract, so nothing was applied.',
        ),
        provider_unavailable: t(
            'The provider could not be reached, so no payment was started.',
        ),
        unknown_payment: t('The provider has no record of this payment.'),
        contract_not_awaiting: t(
            'The contract was no longer awaiting funding when this payment was verified.',
        ),
    };
    return (reason: string | null) =>
        reason ? (reasons[reason] ?? null) : null;
}

// The hiring pages share a heading and three tabs. Each tab is its own page, so the current one is a still
// underline rather than a sliding pill (plan 032 part 7).
export function AgreementLayout({
    title,
    lead,
    href = '/offers',
    children,
}: {
    title: string;
    lead?: string;
    href?: string;
    children: ReactNode;
}) {
    const { t } = useTranslation();
    const { url } = usePage();
    const current = url.startsWith('/contracts') ? '/contracts' : '/offers';
    const tabs: [string, string][] = [
        ['/offers', t('Final offers')],
        ['/contracts', t('Contracts')],
        ['/messages', t('Messages')],
    ];
    return (
        <AppLayout breadcrumbs={[{ title, href }]}>
            <Head title={title} />
            <main className="workspace-dashboard market-workspace market-stack">
                <div>
                    <h1 className="text-2xl font-semibold">{title}</h1>
                    {lead && <p className="hiring-lead">{lead}</p>}
                </div>
                <nav className="hiring-nav" aria-label={t('Hiring')}>
                    {tabs.map(([path, label]) => (
                        <Link
                            key={path}
                            href={path}
                            aria-current={current === path ? 'page' : undefined}
                        >
                            {label}
                        </Link>
                    ))}
                </nav>
                {children}
            </main>
        </AppLayout>
    );
}
// The status of an offer as the viewer reads it, shown with the site's small square (plan 032 part 7).
export function offerState(
    offer: Pick<OfferRow, 'status' | 'is_client' | 'latest' | 'turn'>,
    t: (text: string) => string,
): { label: string; state: OfferState } {
    switch (offer.status) {
        case 'pending':
            return {
                label: offer.is_client
                    ? t('Waiting for their answer')
                    : t('Needs your answer'),
                state: 'open',
            };
        case 'changes_requested':
            if (!offer.latest)
                return {
                    label: t('Replaced by a newer offer'),
                    state: 'draft',
                };
            return {
                label:
                    offer.turn !== null && !offer.is_client
                        ? t('Waiting for a new offer')
                        : t('Changes requested'),
                state: offer.turn === null ? 'draft' : 'closed',
            };
        case 'accepted':
            return { label: t('Offer accepted'), state: 'hired' };
        case 'declined':
            return { label: t('Offer declined'), state: 'draft' };
        case 'withdrawn':
            return { label: t('Offer withdrawn'), state: 'draft' };
        case 'expired':
            return { label: t('Expired'), state: 'draft' };
        case 'restricted':
            return { label: t('Closed by restriction'), state: 'hidden' };
        default:
            return { label: offer.status, state: 'draft' };
    }
}
export function OfferTime({ value }: { value: string }) {
    const { locale } = useTranslation();
    return (
        <time dateTime={value}>
            {new Date(value).toLocaleString(locale, { timeZoneName: 'short' })}
        </time>
    );
}
export function AgreementTerms({ terms }: { terms: Terms }) {
    const { t } = useTranslation();
    return (
        <section className="market-panel market-stack">
            <h2>{t('Agreement')}</h2>
            <dl className="proposal-terms">
                <div>
                    <dt>{t('Fixed price (USD)')}</dt>
                    <dd>
                        <Money min={terms.amount} max={terms.amount} />
                    </dd>
                </div>
                <div>
                    <dt>{t('Delivery duration (calendar days)')}</dt>
                    <dd>{terms.duration_days}</dd>
                </div>
                <div>
                    <dt>{t('Included revision rounds')}</dt>
                    <dd>{terms.revision_rounds}</dd>
                </div>
            </dl>
            <h3>{t('Scope summary')}</h3>
            <p dir="auto" className="market-prose break-words">
                {terms.scope}
            </p>
            <h3>{t('Named deliverables')}</h3>
            <ol className="list-decimal space-y-2 ps-6">
                {terms.deliverables.map((item, index) => (
                    <li key={index} dir="auto" className="break-words">
                        {item}
                    </li>
                ))}
            </ol>
            <p className="market-muted">
                {t(
                    'Delivery time starts only after verified sandbox funding. Weekends count as calendar days.',
                )}
            </p>
        </section>
    );
}

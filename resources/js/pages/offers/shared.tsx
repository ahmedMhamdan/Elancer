// Cards/forms reuse local TailAdmin common/ComponentCard.tsx and form/form-elements/DefaultInputs.tsx.
// Existing adaptations retain Elancer tokens, keyboard controls and RTL. See THIRD_PARTY_NOTICES.md.
import { Head, Link } from '@inertiajs/react';
import {
    CircleCheck,
    CreditCard,
    FlaskConical,
    Landmark,
    WalletCards,
    type LucideIcon,
} from 'lucide-react';
import type { ReactNode } from 'react';
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
};
export type Contract = {
    id: number;
    project_id: number;
    offer_id: number;
    conversation_id: number;
    status: string;
    created_at: string;
    funded_at: string | null;
    delivery_due_at: string | null;
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

export function ContractStatus({ status }: { status: string }) {
    const { t } = useTranslation();
    const labels: Record<string, string> = {
        awaiting_payment: t('Awaiting funding'),
        active: t('Funded and active'),
    };
    return (
        <span
            className={
                status === 'active'
                    ? 'proposal-status proposal-status-submitted'
                    : 'proposal-status'
            }
        >
            {labels[status] ?? status}
        </span>
    );
}
export function PaymentStatus({ status }: { status: string }) {
    const { t } = useTranslation();
    const labels: Record<string, string> = {
        pending: t('Checking payment'),
        succeeded: t('Verified'),
        failed: t('Not completed'),
        cancelled: t('Cancelled'),
        unapplied: t('Verified, needs review'),
    };
    return (
        <span
            className={
                status === 'succeeded'
                    ? 'proposal-status proposal-status-submitted'
                    : 'proposal-status'
            }
        >
            {labels[status] ?? status}
        </span>
    );
}
export function useProviderLabel() {
    const { t } = useTranslation();
    const labels: Record<string, string> = {
        stripe: t('Stripe (test mode)'),
        moyasar: t('Moyasar (test mode)'),
        paypal: t('PayPal (test mode)'),
        simulator: t('Offline test simulator'),
    };
    return (provider: string) => labels[provider] ?? provider;
}
export function useProviderHint() {
    const { t } = useTranslation();
    const hints: Record<string, string> = {
        stripe: t('Pay by card on Stripe’s secure page.'),
        moyasar: t('mada, cards and Apple Pay on Moyasar’s secure page.'),
        paypal: t('Pay with a PayPal test account.'),
        simulator: t('Stand-in used for automated testing.'),
    };
    return (provider: string) => hints[provider] ?? '';
}
// Brand-coloured tiles with generic glyphs identify each provider without reproducing its logo artwork.
const MARKS: Record<string, { color: string; Icon: LucideIcon }> = {
    stripe: { color: '#635bff', Icon: CreditCard },
    moyasar: { color: '#1e9e63', Icon: Landmark },
    paypal: { color: '#003087', Icon: WalletCards },
    simulator: { color: '#626f60', Icon: FlaskConical },
};
export function ProviderMark({ provider }: { provider: string }) {
    const { color, Icon } = MARKS[provider] ?? MARKS.simulator;
    return (
        <span
            className="provider-mark"
            style={{ backgroundColor: color }}
            aria-hidden="true"
        >
            <Icon className="size-5" />
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

export function AgreementLayout({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    const { t } = useTranslation();
    return (
        <AppLayout breadcrumbs={[{ title, href: '/offers' }]}>
            <Head title={title} />
            <main className="workspace-dashboard market-workspace market-stack">
                <h1 className="text-2xl font-semibold">{title}</h1>
                <nav className="market-actions" aria-label={t('Hiring')}>
                    <Link href="/offers">{t('Offers')}</Link>
                    <Link href="/contracts">{t('Contracts')}</Link>
                    <Link href="/messages">{t('Messages')}</Link>
                </nav>
                {children}
            </main>
        </AppLayout>
    );
}
export function OfferStatus({ status }: { status: string }) {
    const { t } = useTranslation();
    const labels: Record<string, string> = {
        pending: t('Pending'),
        accepted: t('Accepted'),
        declined: t('Declined'),
        withdrawn: t('Withdrawn'),
        expired: t('Expired'),
        changes_requested: t('Changes requested'),
        restricted: t('Closed by restriction'),
    };
    return <span className="proposal-status">{labels[status] ?? status}</span>;
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

import { Link, router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import Button from '@/components/tailadmin/button';
import { useTranslation } from '@/hooks/use-translation';
import {
    AgreementLayout,
    AgreementTerms,
    ContractStatus,
    OfferTime,
    type Contract,
    type Payment,
} from '@/pages/offers/shared';
import {
    AmendmentHistory,
    AmendmentRequest,
    AmendmentStatus,
    type Amendment,
} from './amendments';
import {
    CancellationRequest,
    CancellationStatus,
    type Cancellation,
} from './cancellation';
import { Deliveries, type Submission } from './deliveries';
import { Funding } from './funding';
import { Reviews, type ReviewState } from './reviews';

type Activity = { kind: string; at: string; number: number | null };

export default function Show({
    contract,
    payment,
    providers,
    funding_paused,
    submissions,
    activity,
    reviews,
    cancellation,
    amendments,
}: {
    contract: Contract;
    payment: Payment | null;
    providers: string[];
    funding_paused: boolean;
    submissions: Submission[];
    activity: Activity[];
    reviews: ReviewState | null;
    cancellation: Cancellation | null;
    amendments: Amendment[];
}) {
    const { t, locale } = useTranslation();
    const tabs = [
        { key: 'deliveries', label: t('Deliveries') },
        { key: 'agreement', label: t('Agreement') },
        { key: 'payments', label: t('Payments') },
        { key: 'activity', label: t('Activity') },
        ...(reviews ? [{ key: 'reviews', label: t('Reviews') }] : []),
    ];
    const [tab, setTab] = useState(
        contract.status === 'awaiting_payment'
            ? 'payments'
            : contract.status === 'completed'
              ? 'reviews'
              : 'deliveries',
    );
    const list = useRef<HTMLDivElement>(null);
    const move = (step: number) => {
        const index = tabs.findIndex(({ key }) => key === tab);
        const next = tabs[(index + step + tabs.length) % tabs.length].key;
        setTab(next);
        list.current
            ?.querySelector<HTMLElement>(`#contract-tab-${next}`)
            ?.focus();
    };
    const [reposting, setReposting] = useState(false);
    const pending = amendments.find(({ status }) => status === 'pending');
    const amendable = ['active', 'submitted', 'revision_requested'].includes(
        contract.status,
    );
    const mine = contract.is_client ? 'client' : 'freelancer';
    // What this participant should do next, by state and role.
    const next: Record<string, Record<string, string>> = {
        awaiting_payment: {
            client: t('Fund the contract to start the work.'),
            freelancer: t(
                'Waiting for the client to fund this contract. The delivery clock has not started.',
            ),
        },
        active: {
            client: t('The freelancer is working on the first delivery.'),
            freelancer: t(
                'Submit the complete delivery before the first delivery date.',
            ),
        },
        submitted: {
            client: t(
                'Review the delivery, then approve it or request a revision.',
            ),
            freelancer: t('Waiting for the client to review your delivery.'),
        },
        revision_requested: {
            client: t('The freelancer is working on your requested changes.'),
            freelancer: t(
                'Read the requested changes and submit a revised delivery.',
            ),
        },
        completed: {
            client: t('The contract is completed. Leave a review.'),
            freelancer: t('The contract is completed. Leave a review.'),
        },
        cancellation_pending: {
            client: t('A cancellation request is open. Formal work is paused.'),
            freelancer: t(
                'A cancellation request is open. Formal work is paused.',
            ),
        },
        cancelled: {
            client: t('This contract was cancelled. Nothing further is due.'),
            freelancer: t(
                'This contract was cancelled. Nothing further is due.',
            ),
        },
    };
    const cancellable = [
        'awaiting_payment',
        'active',
        'submitted',
        'revision_requested',
    ].includes(contract.status);
    const showCancellation =
        cancellation &&
        (['pending', 'accepted'].includes(cancellation.status) ||
            contract.status === 'cancelled');
    const events: Record<string, string> = {
        accepted: t('Final offer accepted'),
        funded: t('Test payment verified'),
        delivered: t('Delivery :number submitted'),
        revision: t('Revision request :number sent'),
        completed: t('Delivery approved and contract completed'),
        cancellation_requested: t('Cancellation requested'),
        cancellation_declined: t('Cancellation request declined'),
        cancellation_withdrawn: t('Cancellation request withdrawn'),
        cancellation_accepted: t('Cancellation accepted'),
        refunded: t('Test payment refunded'),
        cancelled: t('Contract cancelled'),
        amendment_proposed: t('Change to the contract proposed'),
        amendment_accepted: t('Proposed change accepted'),
        amendment_declined: t('Proposed change declined'),
        amendment_withdrawn: t('Proposed change withdrawn'),
    };
    return (
        <AgreementLayout title={t('Contract')}>
            <section className="market-panel market-stack">
                <div className="market-actions">
                    <h2 className="!mb-0" dir="auto">
                        {contract.agreement.project_title}
                    </h2>
                    <ContractStatus status={contract.status} />
                    {contract.overdue && (
                        <span className="proposal-status contract-overdue">
                            {t('First delivery overdue')}
                        </span>
                    )}
                    {contract.revision_overdue && (
                        <span className="proposal-status contract-overdue">
                            {t('Revision overdue')}
                        </span>
                    )}
                </div>
                <dl className="proposal-terms">
                    <div>
                        <dt>{t('Client')}</dt>
                        <dd dir="auto">{contract.agreement.client_name}</dd>
                    </div>
                    <div>
                        <dt>{t('Freelancer')}</dt>
                        <dd dir="auto">{contract.agreement.freelancer_name}</dd>
                    </div>
                    {contract.delivery_due_at && (
                        <div>
                            <dt>{t('First delivery due')}</dt>
                            <dd>
                                <OfferTime value={contract.delivery_due_at} />
                            </dd>
                        </div>
                    )}
                    {contract.status === 'revision_requested' &&
                        contract.revision_due_at && (
                            <div>
                                <dt>{t('Revision due')}</dt>
                                <dd>
                                    <OfferTime
                                        value={contract.revision_due_at}
                                    />
                                </dd>
                            </div>
                        )}
                    {contract.completed_at && (
                        <div>
                            <dt>{t('Completed at')}</dt>
                            <dd>
                                <OfferTime value={contract.completed_at} />
                            </dd>
                        </div>
                    )}
                </dl>
                <p>
                    <strong>{t('Next step')}:</strong>{' '}
                    {next[contract.status]?.[mine]}
                </p>
                <div className="market-actions">
                    <Link
                        className="market-primary-link"
                        href={`/messages/${contract.conversation_id}`}
                    >
                        {t('Open messages')}
                    </Link>
                    {contract.status === 'cancelled' && contract.is_client && (
                        <Button
                            variant="outline"
                            disabled={reposting}
                            onClick={() => {
                                setReposting(true);
                                router.post(
                                    `/contracts/${contract.id}/repost`,
                                    {},
                                    { onFinish: () => setReposting(false) },
                                );
                            }}
                        >
                            {t('Post this project again')}
                        </Button>
                    )}
                </div>
                {contract.status === 'cancelled' && contract.is_client && (
                    <p className="market-muted">
                        {t(
                            'Posting again copies the brief into a new private draft for you to review. This project and contract stay as history.',
                        )}
                    </p>
                )}
            </section>
            {pending && (
                <AmendmentStatus contract={contract} amendment={pending} />
            )}
            {showCancellation && (
                <CancellationStatus
                    contract={contract}
                    cancellation={cancellation}
                />
            )}
            <div
                ref={list}
                role="tablist"
                aria-label={t('Contract sections')}
                className="contract-tabs"
                onKeyDown={(event) => {
                    const forward =
                        document.documentElement.dir === 'rtl'
                            ? 'ArrowLeft'
                            : 'ArrowRight';
                    if (event.key === 'ArrowLeft' || event.key === 'ArrowRight')
                        move(event.key === forward ? 1 : -1);
                }}
            >
                {tabs.map(({ key, label }) => (
                    <button
                        key={key}
                        type="button"
                        role="tab"
                        id={`contract-tab-${key}`}
                        aria-selected={tab === key}
                        aria-controls="contract-panel"
                        tabIndex={tab === key ? 0 : -1}
                        onClick={() => setTab(key)}
                    >
                        {label}
                    </button>
                ))}
            </div>
            <div
                id="contract-panel"
                role="tabpanel"
                aria-labelledby={`contract-tab-${tab}`}
                className="market-stack"
            >
                {tab === 'deliveries' && (
                    <Deliveries contract={contract} submissions={submissions} />
                )}
                {tab === 'agreement' && (
                    <>
                        <AgreementTerms terms={contract.agreement} />
                        <div className="market-actions">
                            <Link href={`/offers/${contract.offer_id}`}>
                                {t('View accepted offer')}
                            </Link>
                            <Link href={`/jobs/${contract.project_id}`}>
                                {t('View project')}
                            </Link>
                        </div>
                        <AmendmentHistory amendments={amendments} />
                        {amendable && !pending && (
                            <AmendmentRequest contract={contract} />
                        )}
                        {cancellable && (
                            <CancellationRequest contract={contract} />
                        )}
                    </>
                )}
                {tab === 'payments' && (
                    <>
                        <Funding
                            contract={contract}
                            payment={payment}
                            providers={providers}
                            funding_paused={funding_paused}
                        />
                        <div className="market-actions">
                            <Link href="/finance">{t('Finance')}</Link>
                        </div>
                    </>
                )}
                {tab === 'activity' && (
                    <section className="market-panel market-stack">
                        <h2>{t('Activity')}</h2>
                        <ol className="contract-activity">
                            {activity.map((event, index) => (
                                <li key={index}>
                                    <span>
                                        {(
                                            events[event.kind] ?? event.kind
                                        ).replace(
                                            ':number',
                                            (event.number ?? 0).toLocaleString(
                                                locale,
                                            ),
                                        )}
                                    </span>
                                    <span className="market-muted">
                                        <OfferTime value={event.at} />
                                    </span>
                                </li>
                            ))}
                        </ol>
                    </section>
                )}
                {tab === 'reviews' && reviews && (
                    <Reviews contract={contract} reviews={reviews} />
                )}
            </div>
        </AgreementLayout>
    );
}

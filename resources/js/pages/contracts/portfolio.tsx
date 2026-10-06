import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { CaseBody, type CaseContent } from '@/components/portfolio-case';
import Button from '@/components/tailadmin/button';
import { useTranslation } from '@/hooks/use-translation';
import { OfferTime, type Contract } from '@/pages/offers/shared';
import { ApprovalHistory, type Approval } from '@/pages/portfolio/shared';

export type PortfolioState = {
    case_id: number | null;
    pending: { id: number; content: CaseContent; created_at: string } | null;
    public: CaseContent | null;
    hidden: boolean;
    revoked_at: string | null;
    history: Approval[];
};

function Preview({ title, content }: { title: string; content: CaseContent }) {
    return (
        <div className="market-stack delivery-revision">
            <p className="market-muted">{title}</p>
            <h3 className="!mb-0" dir="auto">
                {content.title}
            </h3>
            <CaseBody content={content} />
        </div>
    );
}

// Q52, Q53: the client approves the exact content and can withdraw permission later.
export function Portfolio({
    contract,
    portfolio,
}: {
    contract: Contract;
    portfolio: PortfolioState;
}) {
    const { t } = useTranslation();
    const errors = usePage().props.errors as Record<string, string>;
    const [busy, setBusy] = useState(false);
    const send = (action: string, approval?: number) => {
        if (busy) return;
        setBusy(true);
        router.patch(
            `/contracts/${contract.id}/portfolio`,
            { action, approval },
            { preserveScroll: true, onFinish: () => setBusy(false) },
        );
    };
    const name = contract.agreement.freelancer_name;
    return (
        <section className="market-panel market-stack">
            <h2 className="!mb-0">{t('Portfolio')}</h2>
            <p className="market-muted">
                {contract.is_client
                    ? t(
                          'A case study about this work is public only while you allow it. It never shows your name, the price or anything from this contract unless it is written in the text you approve.',
                      )
                    : t(
                          'A case study about this work becomes public only when the client approves the exact text and links.',
                      )}
            </p>
            {errors.portfolio && (
                <p role="alert" className="market-error">
                    {errors.portfolio}
                </p>
            )}
            {!contract.is_client && (
                <div className="market-actions">
                    <Link
                        href={
                            portfolio.case_id
                                ? '/my-portfolio'
                                : `/my-portfolio/create?contract=${contract.id}`
                        }
                    >
                        {portfolio.case_id
                            ? t('Manage this case study in your portfolio')
                            : t('Write a case study')}
                    </Link>
                </div>
            )}
            {portfolio.pending && (
                <>
                    <p>
                        {contract.is_client
                            ? t(
                                  ':name asks to publish this case study. Read it as visitors would see it.',
                                  { name },
                              )
                            : t(
                                  'Waiting for the client to answer your request.',
                              )}{' '}
                        <span className="market-muted">
                            <OfferTime value={portfolio.pending.created_at} />
                        </span>
                    </p>
                    <Preview
                        title={t('Awaiting approval')}
                        content={portfolio.pending.content}
                    />
                    {contract.is_client && (
                        <div className="market-actions">
                            <Button
                                disabled={busy}
                                onClick={() =>
                                    send('approve', portfolio.pending?.id)
                                }
                            >
                                {t('Approve and publish')}
                            </Button>
                            <Button
                                variant="outline"
                                disabled={busy}
                                onClick={() =>
                                    send('decline', portfolio.pending?.id)
                                }
                            >
                                {t('Decline')}
                            </Button>
                        </div>
                    )}
                </>
            )}
            {portfolio.public && (
                <>
                    <Preview
                        title={
                            portfolio.hidden
                                ? t(
                                      'Approved version, currently hidden by the freelancer',
                                  )
                                : t('Approved version, currently public')
                        }
                        content={portfolio.public}
                    />
                    {contract.is_client && (
                        <div className="market-actions">
                            <Button
                                variant="danger-outline"
                                disabled={busy}
                                onClick={() => {
                                    if (
                                        window.confirm(
                                            t(
                                                'Withdraw permission? The case study stops being public at once.',
                                            ),
                                        )
                                    )
                                        send('revoke');
                                }}
                            >
                                {t('Withdraw permission')}
                            </Button>
                        </div>
                    )}
                </>
            )}
            {!portfolio.public && portfolio.revoked_at && (
                <p>
                    {t(
                        'Permission was withdrawn. The case study is not public.',
                    )}{' '}
                    <span className="market-muted">
                        <OfferTime value={portfolio.revoked_at} />
                    </span>
                </p>
            )}
            <ApprovalHistory history={portfolio.history} />
        </section>
    );
}

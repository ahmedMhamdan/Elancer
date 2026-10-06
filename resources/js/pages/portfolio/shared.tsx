import type { ReactNode } from 'react';
import type { CaseContent } from '@/components/portfolio-case';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import { OfferTime } from '@/pages/offers/shared';
import '../../../css/elancer-marketplace.css';
import '../../../css/elancer-jobs.css';

export type Approval = {
    id: number;
    status: string;
    created_at: string;
    decided_at: string | null;
};
export type OwnedCase = {
    id: number;
    content: CaseContent;
    public_content: CaseContent | null;
    published_at: string | null;
    hidden_at: string | null;
    revoked_at: string | null;
    changed: boolean;
    contract: { id: number; title: string } | null;
    pending: boolean;
    history: Approval[];
    deletable: boolean;
};

export function PortfolioLayout({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    const { t } = useTranslation();
    return (
        <AppLayout
            breadcrumbs={[{ title: t('Portfolio'), href: '/my-portfolio' }]}
        >
            <main className="workspace-dashboard market-workspace">
                <header className="workspace-page-heading">
                    <h1>{title}</h1>
                </header>
                {children}
            </main>
        </AppLayout>
    );
}

// The one state a visitor would experience, then what is waiting behind it.
export function CaseStatus({ item }: { item: OwnedCase }) {
    const { t } = useTranslation();
    const state = item.public_content
        ? item.hidden_at
            ? 'hidden'
            : 'public'
        : item.revoked_at
          ? 'revoked'
          : 'draft';
    const labels: Record<string, string> = {
        public: t('Public'),
        hidden: t('Hidden'),
        revoked: t('Permission withdrawn'),
        draft: t('Draft'),
    };
    return (
        <>
            <span
                className={`proposal-status ${state === 'public' ? 'proposal-status-submitted' : ''}`}
            >
                {labels[state]}
            </span>
            {item.pending && (
                <span className="proposal-status">
                    {t('Awaiting client approval')}
                </span>
            )}
            {item.changed && !item.pending && (
                <span className="proposal-status">
                    {t('Unpublished changes')}
                </span>
            )}
        </>
    );
}

// Q53: every request and its outcome stays listed for both participants.
export function ApprovalHistory({ history }: { history: Approval[] }) {
    const { t } = useTranslation();
    const labels: Record<string, string> = {
        pending: t('Awaiting the client'),
        approved: t('Approved'),
        declined: t('Declined'),
        withdrawn: t('Withdrawn'),
        revoked: t('Approved, then permission withdrawn'),
        closed: t('Closed when permission was withdrawn'),
    };
    if (!history.length) return null;
    return (
        <div className="market-stack">
            <h3 className="!mb-0">{t('Approval history')}</h3>
            <ol className="contract-activity">
                {history.map((approval) => (
                    <li key={approval.id}>
                        <span>
                            {labels[approval.status] ?? approval.status}
                        </span>
                        <span className="market-muted">
                            <OfferTime
                                value={
                                    approval.decided_at ?? approval.created_at
                                }
                            />
                        </span>
                    </li>
                ))}
            </ol>
        </div>
    );
}

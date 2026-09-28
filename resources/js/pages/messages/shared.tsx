import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/hooks/use-translation';
import '../../../css/elancer-marketplace.css';

export type ConversationSummary = {
    id: number;
    project: { id: number; title: string };
    proposal_id: number;
    contract_id: number | null;
    counterpart: string;
    archived: boolean;
    unread: number;
    updated_at: string;
};
export type Message = {
    id: number;
    body: string;
    version: number;
    created_at: string;
    edited_at: string | null;
    mine: boolean;
    can_edit: boolean;
    revisions: { body: string; version: number; created_at: string }[];
};

export function MessageLayout({ children }: { children: ReactNode }) {
    const { t } = useTranslation();
    return (
        <AppLayout breadcrumbs={[{ title: t('Messages'), href: '/messages' }]}>
            <Head title={t('Messages')} />
            <main className="workspace-dashboard market-workspace market-stack">
                <h1 className="text-2xl font-semibold">{t('Messages')}</h1>
                <nav className="market-actions" aria-label={t('Messages')}>
                    <Link href="/messages">{t('Inbox')}</Link>
                    <Link href="/messages?kind=hiring">{t('Hiring')}</Link>
                    <Link href="/messages?kind=contracts">
                        {t('Contracts')}
                    </Link>
                    <Link href="/messages?archived=1">
                        {t('Archived conversations')}
                    </Link>
                </nav>
                {children}
            </main>
        </AppLayout>
    );
}

import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/hooks/use-translation';
import '../../../css/elancer-marketplace.css';
import '../../../css/elancer-chat.css';

export type ConversationSummary = {
    id: number;
    project: { id: number; title: string };
    proposal_id: number;
    contract_id: number | null;
    counterpart: string;
    preview: string;
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
            <div className="workspace-dashboard market-workspace chat-workspace min-w-0 !p-3 sm:!p-6">
                <h1 className="sr-only">{t('Messages')}</h1>
                {children}
            </div>
        </AppLayout>
    );
}

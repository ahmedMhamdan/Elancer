import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/hooks/use-translation';
import type { Attachment } from '@/pages/discovery/shared';
import '../../../css/elancer-marketplace.css';
import '../../../css/elancer-chat.css';

export type ConversationSummary = {
    id: number;
    project: { id: number; title: string };
    proposal_id: number;
    contract_id: number | null;
    counterpart: string;
    // Plan 032 part 13: the photo the freelancer's proposal carried, for the client only (client photos stay private).
    avatar: string | null;
    is_client: boolean;
    // The contract's state, or null while it is still a hiring conversation.
    status: string | null;
    // Whose move it is: the contract's, or on the open conversation the next hiring step.
    turn: 'yours' | 'theirs' | null;
    // The contract's agreed amount, or the proposal's price.
    amount: string | null;
    due_at: string | null;
    preview: string;
    // The latest message was hidden by moderation, so there is no text to preview.
    preview_hidden: boolean;
    // Plan 030: the latest message is files alone, so its preview names how many.
    preview_files: number;
    preview_mine: boolean;
    archived: boolean;
    unread: number;
    updated_at: string;
    // The open conversation only: its latest final offer before a contract.
    offer?: { id: number; status: string } | null;
};
export type Message = {
    id: number;
    // Null when moderation hid the message: participants receive a notice, never the text.
    body: string | null;
    hidden: boolean;
    version: number;
    created_at: string;
    edited_at: string | null;
    mine: boolean;
    can_edit: boolean;
    revisions: { body: string; version: number; created_at: string }[];
    // Plan 030: none for a message moderation hid.
    files: Attachment[];
};
/** Plan 032 part 13: the conversation's details panel. */
export type ConversationDetails = {
    // The freelancer's public profile, linked only while it is public.
    profile: number | null;
    headline: string | null;
    country: string | null;
    company: string | null;
    record: {
        completed: number;
        rating: { count: number; average: number | null };
    };
    files: Attachment[];
    files_total: number;
};

export function MessageLayout({ children }: { children: ReactNode }) {
    const { t } = useTranslation();
    return (
        <AppLayout breadcrumbs={[{ title: t('Messages'), href: '/messages' }]}>
            <Head title={t('Messages')} />
            <div className="workspace-dashboard market-workspace chat-workspace min-w-0">
                <h1 className="sr-only">{t('Messages')}</h1>
                {children}
            </div>
        </AppLayout>
    );
}

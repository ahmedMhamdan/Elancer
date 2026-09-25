import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/hooks/use-translation';
import '../../../css/elancer-marketplace.css';

export type Invitation = {
    id: number;
    status: string;
    version: number;
    sent_at: string;
    next_resend_at: string | null;
    client_name: string;
    recipient_name: string;
    project: { id: number; title: string };
};

export function InvitationLayout({ children }: { children: ReactNode }) {
    const { t } = useTranslation();
    return (
        <AppLayout
            breadcrumbs={[{ title: t('Invitations'), href: '/invitations' }]}
        >
            <Head title={t('Invitations')} />
            <main className="workspace-dashboard market-workspace market-stack">
                <h1 className="text-2xl font-semibold">{t('Invitations')}</h1>
                <nav className="market-actions" aria-label={t('Invitations')}>
                    <Link href="/invitations">{t('Received invitations')}</Link>
                    <Link href="/invitations?box=sent">
                        {t('Sent invitations')}
                    </Link>
                    <Link href="/blocked-accounts">
                        {t('Blocked accounts')}
                    </Link>
                </nav>
                {children}
            </main>
        </AppLayout>
    );
}

export function InvitationStatus({ status }: { status: string }) {
    const { t } = useTranslation();
    const labels: Record<string, string> = {
        pending: t('Pending'),
        accepted: t('Accepted'),
        declined: t('Declined'),
        cancelled: t('Cancelled'),
    };
    return <span className="proposal-status">{labels[status] ?? status}</span>;
}

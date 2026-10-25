import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import type { Rating } from '@/components/rating-summary';
import AppLayout from '@/layouts/app-layout';
import { useDemoSession } from '@/hooks/use-demo-session';
import { useTranslation } from '@/hooks/use-translation';
import type { Job } from '@/pages/discovery/shared';
import '../../../css/elancer-marketplace.css';

// Where an invitation stands for both sides (plan 032 part 14): it waits while its project takes applications; applied,
// declined, or closed once the project closed or a block cancelled it.
export type InvitationState = 'waiting' | 'applied' | 'declined' | 'closed';
// The other side as the viewer met them: a client sees the freelancer's public photo, a freelancer the client's company.
export type Counterpart = {
    name: string;
    avatar: string | null;
    company: string | null;
};
export type InvitationRow = {
    id: number;
    status: string;
    sent_at: string;
    state: InvitationState;
    state_at: string | null;
    project: { id: number; title: string };
    counterpart: Counterpart;
};
export type Invitation = Omit<InvitationRow, 'project'> & {
    version: number;
    next_resend_at: string | null;
    // A project that left public pages lends the invitation its title only.
    project:
        | Job
        | (Pick<Job, 'id' | 'title' | 'open'> & {
              excerpt?: undefined;
          });
    public: boolean;
    person: {
        name: string;
        avatar: string | null;
        headline: string | null;
        country: string | null;
        company: string | null;
        profile: number | null;
        completed: number;
        rating: Rating;
    };
    proposal: { id: number; price: string | null } | null;
    can_respond: boolean;
    can_decline: boolean;
    can_resend: boolean;
    events: { kind: string; created_at: string }[];
};

// The Invite and Blocked accounts pages keep the Invitations heading and links.
export function InvitationLayout({ children }: { children: ReactNode }) {
    const { t } = useTranslation();
    // Plan 032 part 10: a shared demo account blocks and unblocks nobody.
    const { demo } = useDemoSession();
    return (
        <AppLayout
            breadcrumbs={[{ title: t('Invitations'), href: '/invitations' }]}
        >
            <Head title={t('Invitations')} />
            <main className="workspace-dashboard market-workspace market-stack">
                <h1 className="text-2xl font-semibold">{t('Invitations')}</h1>
                <nav className="market-actions" aria-label={t('Invitations')}>
                    <Link href="/invitations?box=received">
                        {t('Received invitations')}
                    </Link>
                    <Link href="/invitations?box=sent">
                        {t('Sent invitations')}
                    </Link>
                    {!demo && (
                        <Link href="/blocked-accounts">
                            {t('Blocked accounts')}
                        </Link>
                    )}
                </nav>
                {children}
            </main>
        </AppLayout>
    );
}

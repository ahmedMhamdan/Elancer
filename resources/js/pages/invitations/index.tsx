import { Link } from '@inertiajs/react';
import Pagination from '@/components/tailadmin/pagination';
import { useTranslation } from '@/hooks/use-translation';
import { JobDate, type Page } from '@/pages/discovery/shared';
import { InvitationLayout, InvitationStatus, type Invitation } from './shared';

export default function Index({
    invitations,
    box,
}: {
    invitations: Page<Invitation>;
    box: 'received' | 'sent';
}) {
    const { t } = useTranslation();
    return (
        <InvitationLayout>
            <h2>
                {box === 'sent'
                    ? t('Sent invitations')
                    : t('Received invitations')}
            </h2>
            {!invitations.data.length && (
                <p className="market-panel">{t('No invitations yet.')}</p>
            )}
            {invitations.data.map((invitation) => (
                <article
                    key={invitation.id}
                    className="market-panel market-stack"
                >
                    <div className="market-actions">
                        <InvitationStatus status={invitation.status} />
                        <JobDate value={invitation.sent_at} />
                    </div>
                    <h2>
                        <Link href={`/invitations/${invitation.id}`} dir="auto">
                            {invitation.project.title}
                        </Link>
                    </h2>
                    <p dir="auto">
                        {box === 'sent'
                            ? invitation.recipient_name
                            : invitation.client_name}
                    </p>
                    <Link href={`/invitations/${invitation.id}`}>
                        {t('View invitation')}
                    </Link>
                </article>
            ))}
            <Pagination data={invitations} />
        </InvitationLayout>
    );
}

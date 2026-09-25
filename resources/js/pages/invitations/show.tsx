import { Link, useForm, router } from '@inertiajs/react';
import Button from '@/components/tailadmin/button';
import { useTranslation } from '@/hooks/use-translation';
import { JobDate } from '@/pages/discovery/shared';
import { InvitationLayout, InvitationStatus, type Invitation } from './shared';

export default function Show({
    invitation,
    author,
    available,
    events,
}: {
    invitation: Invitation;
    author: boolean;
    available: boolean;
    events: { kind: string; created_at: string }[];
}) {
    const { t, locale } = useTranslation();
    const form = useForm({
        action: author ? 'resend' : 'decline',
        version: invitation.version,
    });
    const respond = () => {
        form.transform((data) => ({ ...data, version: invitation.version }));
        form.patch(`/invitations/${invitation.id}`, { preserveScroll: true });
    };
    const eventLabels: Record<string, string> = {
        sent: t('Invitation sent'),
        resent: t('Invitation resent'),
        declined: t('Declined'),
        accepted: t('Accepted'),
        cancelled: t('Cancelled'),
    };
    return (
        <InvitationLayout>
            <section className="market-panel market-stack">
                <div>
                    <Button
                        variant="danger-outline"
                        onClick={() => {
                            if (
                                window.confirm(
                                    t(
                                        'Block this account? New invitations and proposal contact will be stopped.',
                                    ),
                                )
                            )
                                router.post(
                                    `/invitations/${invitation.id}/block`,
                                );
                        }}
                    >
                        {t('Block account')}
                    </Button>
                </div>
                <InvitationStatus status={invitation.status} />
                <h2 dir="auto">{invitation.project.title}</h2>
                <p dir="auto">
                    {author
                        ? invitation.recipient_name
                        : invitation.client_name}
                </p>
                <Link href={`/jobs/${invitation.project.id}`}>
                    {t('View project')}
                </Link>
                {!available && (
                    <p>{t('This invitation is not currently actionable.')}</p>
                )}
                {!author && invitation.status === 'pending' && (
                    <div className="market-actions">
                        {available && (
                            <Link
                                className="job-button"
                                href={`/jobs/${invitation.project.id}/apply`}
                            >
                                {t('Respond with a proposal')}
                            </Link>
                        )}
                        <Button
                            variant="outline"
                            disabled={form.processing}
                            onClick={respond}
                        >
                            {t('Decline invitation')}
                        </Button>
                    </div>
                )}
                {author && invitation.status === 'declined' && (
                    <>
                        <p>
                            {t('Next eligible resend time')}:{' '}
                            <bdi>
                                {invitation.next_resend_at &&
                                    new Date(
                                        invitation.next_resend_at,
                                    ).toLocaleString(locale, {
                                        timeZoneName: 'short',
                                    })}
                            </bdi>
                        </p>
                        <div>
                            <Button
                                disabled={
                                    !available ||
                                    form.processing ||
                                    (!!invitation.next_resend_at &&
                                        Date.parse(invitation.next_resend_at) >
                                            Date.now())
                                }
                                onClick={respond}
                            >
                                {t('Resend invitation')}
                            </Button>
                        </div>
                    </>
                )}
                <p className="market-muted">
                    {t(
                        'An invitation is accepted only when a proposal is submitted.',
                    )}
                </p>
            </section>
            <section className="market-panel">
                <h2>{t('Invitation history')}</h2>
                <ol className="market-stack">
                    {events.map((event, index) => (
                        <li key={index} className="market-actions">
                            <span>{eventLabels[event.kind] ?? event.kind}</span>
                            <JobDate value={event.created_at} />
                        </li>
                    ))}
                </ol>
            </section>
        </InvitationLayout>
    );
}

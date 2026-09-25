import StartConversation from '@/components/start-conversation';
import { Head, Link, router, useForm } from '@inertiajs/react';
import Button from '@/components/tailadmin/button';
import TextArea from '@/components/tailadmin/textarea';
import { useTranslation } from '@/hooks/use-translation';
import { JobDate } from '@/pages/discovery/shared';
import {
    ProposalLayout,
    ProposalStatus,
    ContentDetails,
    ProjectContext,
    type Proposal,
    type ProposalProject,
} from './shared';
export default function Show({
    proposal,
    project,
    author,
    canEdit,
    canReview,
    conversationId,
    canStartConversation,
}: {
    proposal: Proposal;
    project: ProposalProject;
    author: boolean;
    canEdit: boolean;
    canReview: boolean;
    conversationId: number | null;
    canStartConversation: boolean;
}) {
    const { t } = useTranslation();
    const form = useForm({
        action: 'organize',
        organization: proposal.organization ?? 'received',
        client_note: proposal.client_note ?? '',
        version: proposal.version,
    });
    const review = (action: string) => {
        form.transform((data) => ({
            ...data,
            action,
            version: proposal.version,
        }));
        form.patch(`/proposals/${proposal.id}/review`, {
            preserveScroll: true,
        });
    };
    const events: Record<string, string> = {
        submitted: t('Submitted'),
        resubmitted: t('Resubmitted'),
        revised: t('Revised'),
        withdrawn: t('Withdrawn'),
        declined: t('Declined'),
        reopened: t('Reopened'),
    };
    return (
        <ProposalLayout title={t('Proposal details')}>
            <Head title={t('Proposal details')} />
            <div className="market-stack">
                <StartConversation
                    proposalId={proposal.id}
                    conversationId={conversationId}
                    canStart={canStartConversation}
                />
                <Link
                    href={
                        author
                            ? '/my-proposals'
                            : `/my-projects/${project.id}/proposals`
                    }
                >
                    {t(author ? 'My proposals' : 'All applicants')}
                </Link>
                <div className="market-actions">
                    <ProposalStatus status={proposal.status} />
                    {canEdit && (
                        <Link
                            className="job-primary-link"
                            href={`/jobs/${project.id}/apply`}
                        >
                            {t(
                                proposal.submitted_at
                                    ? 'Edit proposal'
                                    : 'Continue draft',
                            )}
                        </Link>
                    )}
                    {author &&
                        canEdit &&
                        ['submitted', 'reopened'].includes(proposal.status) && (
                            <Button
                                variant="danger-outline"
                                onClick={() => {
                                    if (
                                        window.confirm(
                                            t(
                                                'Withdraw this proposal? The client will retain its submitted history.',
                                            ),
                                        )
                                    )
                                        router.post(
                                            `/proposals/${proposal.id}/withdraw`,
                                            { version: proposal.version },
                                        );
                                }}
                            >
                                {t('Withdraw proposal')}
                            </Button>
                        )}
                </div>
                <div className="market-two-column">
                    <div className="market-stack">
                        <section className="market-panel market-stack">
                            {proposal.profile_snapshot && (
                                <header>
                                    <h2 dir="auto">
                                        {proposal.profile_snapshot.name}
                                    </h2>
                                    <p dir="auto">
                                        {proposal.profile_snapshot.headline}
                                    </p>
                                    <div className="job-skills">
                                        {proposal.profile_snapshot.skills.map(
                                            (skill) => (
                                                <span key={skill.id} dir="auto">
                                                    {skill.name}
                                                </span>
                                            ),
                                        )}
                                    </div>
                                </header>
                            )}
                            {proposal.content ? (
                                <ContentDetails
                                    content={proposal.content}
                                    project={project}
                                />
                            ) : proposal.draft ? (
                                <>
                                    <p>{t('Private draft')}</p>
                                    <ContentDetails
                                        content={proposal.draft}
                                        project={project}
                                    />
                                </>
                            ) : (
                                <p>{t('No proposal content yet.')}</p>
                            )}
                            {author && proposal.content && proposal.draft && (
                                <p className="market-muted">
                                    {t(
                                        'You have private draft changes. Submit them to update what the client sees.',
                                    )}
                                </p>
                            )}
                        </section>
                        <section className="market-panel">
                            <h2>{t('Submission history')}</h2>
                            {!proposal.events.length && (
                                <p>
                                    {t(
                                        'Nothing has been shared with the client yet.',
                                    )}
                                </p>
                            )}
                            {proposal.events.map((event, i) => (
                                <details key={i} className="proposal-history">
                                    <summary>
                                        {events[event.kind] ?? event.kind} ·{' '}
                                        <JobDate value={event.created_at} />
                                    </summary>
                                    {event.content && (
                                        <ContentDetails
                                            content={event.content}
                                            project={project}
                                        />
                                    )}
                                </details>
                            ))}
                        </section>
                    </div>
                    <aside className="market-stack">
                        <ProjectContext project={project} />
                        {canReview && (
                            <section className="market-panel market-stack">
                                <h2>{t('Private client review')}</h2>
                                <label className="market-field">
                                    <span>{t('Organize applicant')}</span>
                                    <select
                                        value={form.data.organization}
                                        onChange={(e) =>
                                            form.setData(
                                                'organization',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <option value="received">
                                            {t('Received')}
                                        </option>
                                        <option value="shortlisted">
                                            {t('Shortlisted')}
                                        </option>
                                        <option value="archived">
                                            {t('Archived')}
                                        </option>
                                    </select>
                                </label>
                                <label className="market-field">
                                    <span>{t('Private notes')}</span>
                                    <TextArea
                                        value={form.data.client_note}
                                        maxLength={5000}
                                        rows={5}
                                        onChange={(v) =>
                                            form.setData('client_note', v)
                                        }
                                    />
                                </label>
                                <p className="market-muted">
                                    {t(
                                        'Only you can see these notes and organization labels.',
                                    )}
                                </p>
                                {Object.values(form.errors).map((error, i) => (
                                    <p role="alert" key={i}>
                                        {error}
                                    </p>
                                ))}
                                <Button
                                    disabled={form.processing}
                                    onClick={() => review('organize')}
                                >
                                    {t('Save review')}
                                </Button>
                                {['submitted', 'reopened'].includes(
                                    proposal.status,
                                ) && (
                                    <Button
                                        variant="danger-outline"
                                        disabled={form.processing}
                                        onClick={() => {
                                            if (
                                                window.confirm(
                                                    t('Decline this proposal?'),
                                                )
                                            )
                                                review('decline');
                                        }}
                                    >
                                        {t('Decline proposal')}
                                    </Button>
                                )}
                                {proposal.status === 'declined' && (
                                    <Button
                                        variant="outline"
                                        disabled={form.processing}
                                        onClick={() => review('reopen')}
                                    >
                                        {t('Reopen proposal')}
                                    </Button>
                                )}
                            </section>
                        )}
                    </aside>
                </div>
            </div>
        </ProposalLayout>
    );
}

import { Head, Link } from '@inertiajs/react';
import { useTranslation } from '@/hooks/use-translation';
import {
    ProposalLayout,
    ProposalStatus,
    ContentDetails,
    type Proposal,
    type ProposalProject,
} from './shared';
export default function Compare({
    project,
    proposals,
}: {
    project: ProposalProject;
    proposals: Proposal[];
}) {
    const { t } = useTranslation();
    return (
        <ProposalLayout title={t('Compare applicants')}>
            <Head title={t('Compare applicants')} />
            <div className="market-stack">
                <h2 dir="auto">{project.title}</h2>
                <Link href={`/my-projects/${project.id}/proposals`}>
                    {t('All applicants')}
                </Link>
                <div className="proposal-compare">
                    {proposals.map((proposal) => (
                        <article
                            className="market-panel market-stack"
                            key={proposal.id}
                        >
                            <ProposalStatus status={proposal.status} />
                            <h2>
                                <Link
                                    href={`/proposals/${proposal.id}`}
                                    dir="auto"
                                >
                                    {proposal.profile_snapshot?.name}
                                </Link>
                            </h2>
                            <p dir="auto">
                                {proposal.profile_snapshot?.headline}
                            </p>
                            <div className="job-skills">
                                {proposal.profile_snapshot?.skills.map(
                                    (skill) => (
                                        <span key={skill.id} dir="auto">
                                            {skill.name}
                                        </span>
                                    ),
                                )}
                            </div>
                            {proposal.content && (
                                <ContentDetails
                                    content={proposal.content}
                                    project={project}
                                />
                            )}
                        </article>
                    ))}
                </div>
            </div>
        </ProposalLayout>
    );
}

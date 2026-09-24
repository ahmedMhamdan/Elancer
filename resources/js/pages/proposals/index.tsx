import { Head, Link } from '@inertiajs/react';
import Pagination from '@/components/tailadmin/pagination';
import { useTranslation } from '@/hooks/use-translation';
import { Money, JobDate, type Page } from '@/pages/discovery/shared';
import {
    ProposalLayout,
    ProposalStatus,
    type ProposalContent,
    type ProposalProject,
} from './shared';
export default function Index({
    proposals,
}: {
    proposals: Page<{
        id: number;
        status: string;
        updated_at: string;
        project: ProposalProject;
        content: ProposalContent | null;
    }>;
}) {
    const { t } = useTranslation();
    return (
        <ProposalLayout title={t('My proposals')}>
            <Head title={t('My proposals')} />
            <div className="market-stack">
                <p className="market-muted">
                    {t(
                        'Track drafts, submitted proposals and client responses in one place.',
                    )}
                </p>
                <Link href="/jobs">{t('Find jobs')}</Link>
                {!proposals.data.length && (
                    <section className="market-panel">
                        <h2>{t('Your next project starts here')}</h2>
                        <p>
                            {t(
                                'Find a project that fits your skills and send a thoughtful proposal.',
                            )}
                        </p>
                    </section>
                )}
                {proposals.data.map((proposal) => (
                    <article key={proposal.id} className="market-panel">
                        <div className="market-actions">
                            <ProposalStatus status={proposal.status} />
                            <JobDate value={proposal.updated_at} />
                        </div>
                        <h2>
                            <Link href={`/proposals/${proposal.id}`} dir="auto">
                                {proposal.project.title}
                            </Link>
                        </h2>
                        {proposal.content?.price && (
                            <Money
                                min={proposal.content.price}
                                max={proposal.content.price}
                            />
                        )}
                        <p className="line-clamp-2" dir="auto">
                            {proposal.content?.message}
                        </p>
                        <Link
                            href={
                                proposal.status === 'draft'
                                    ? `/jobs/${proposal.project.id}/apply`
                                    : `/proposals/${proposal.id}`
                            }
                        >
                            {t(
                                proposal.status === 'draft'
                                    ? 'Continue draft'
                                    : 'View proposal',
                            )}
                        </Link>
                    </article>
                ))}
                <Pagination data={proposals} />
            </div>
        </ProposalLayout>
    );
}

import { Link, router } from '@inertiajs/react';
import Button from '@/components/tailadmin/button';
import Pagination from '@/components/tailadmin/pagination';
import { useTranslation } from '@/hooks/use-translation';
import { JobDate, Money } from '@/pages/discovery/shared';
import type { Job, Page } from '@/pages/discovery/shared';
import { SavedLayout } from './shared';

type Save = {
    id: number;
    saved_at: string;
    // Null once the project has left public pages; none of its content is sent then.
    project: (Job & { hired: boolean }) | null;
    proposal: { id: number; status: string } | null;
};

export default function SavedProjects({ saves }: { saves: Page<Save> }) {
    const { t } = useTranslation();
    const remove = (id: number) =>
        router.delete(`/jobs/${id}/save`, { preserveScroll: true });
    return (
        <SavedLayout
            title={t('Saved projects')}
            intro={t(
                'Projects you saved to come back to. Only you can see this list.',
            )}
        >
            {!saves.data.length && (
                <section className="market-panel">
                    <h3>{t('No saved projects yet.')}</h3>
                    <p className="market-muted">
                        {t(
                            'Save a project from the search results or its page and it appears here.',
                        )}
                    </p>
                    <Link href="/jobs">{t('Find jobs')}</Link>
                </section>
            )}
            {saves.data.map(({ id, saved_at, project, proposal }) => (
                <article key={id} className="market-panel market-stack">
                    {project ? (
                        <>
                            <div className="market-actions">
                                <span
                                    className={
                                        project.open ? 'job-open' : 'job-muted'
                                    }
                                >
                                    {t(
                                        project.hired
                                            ? 'Hired'
                                            : project.open
                                              ? 'Open for applications'
                                              : 'Applications closed',
                                    )}
                                </span>
                                <span className="market-muted">
                                    {t('Saved on')} <JobDate value={saved_at} />
                                </span>
                            </div>
                            <h3>
                                <Link href={`/jobs/${id}`} dir="auto">
                                    {project.title}
                                </Link>
                            </h3>
                            <div className="market-actions">
                                <strong>
                                    <Money
                                        min={project.budget_min}
                                        max={project.budget_max}
                                    />
                                </strong>
                                <span className="market-muted">
                                    {t('Application cutoff')}{' '}
                                    <JobDate
                                        value={project.application_closes_at}
                                    />
                                </span>
                            </div>
                        </>
                    ) : (
                        <p>{t('This project is no longer available.')}</p>
                    )}
                    <div className="market-actions">
                        {project && proposal && (
                            <Link href={`/proposals/${proposal.id}`}>
                                {t('View your proposal')}
                            </Link>
                        )}
                        {project?.open && !proposal && (
                            <Link href={`/jobs/${id}/apply`}>
                                {t('Apply to this project')}
                            </Link>
                        )}
                        <Button
                            variant="outline"
                            aria-label={
                                project
                                    ? t('Remove :name from saved items', {
                                          name: project.title,
                                      })
                                    : undefined
                            }
                            onClick={() => remove(id)}
                        >
                            {t('Remove')}
                        </Button>
                    </div>
                </article>
            ))}
            <Pagination data={saves} />
        </SavedLayout>
    );
}

import { Link, router } from '@inertiajs/react';
import { Bookmark } from 'lucide-react';
import { useState } from 'react';
import { InteractiveHoverButton } from '@/components/ui/interactive-hover-button';
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
    const [removing, setRemoving] = useState<number | null>(null);
    const remove = (id: number) =>
        router.delete(`/jobs/${id}/save`, {
            preserveScroll: true,
            onStart: () => setRemoving(id),
            onFinish: () => setRemoving(null),
        });
    return (
        <SavedLayout
            title={t('Saved projects')}
            intro={t(
                'Projects you saved to come back to. Only you can see this list.',
            )}
        >
            {!saves.data.length && (
                <section className="market-panel saved-empty">
                    <Bookmark size={28} aria-hidden="true" />
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
                <article key={id} className="market-panel saved-project">
                    {project ? (
                        <>
                            <div className="market-actions saved-project-meta">
                                <span
                                    className={`saved-project-status ${project.open ? 'job-open' : 'job-muted'}`}
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
                            <div className="market-actions saved-project-facts">
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
                    <div className="market-actions saved-project-actions">
                        {project && proposal && (
                            <InteractiveHoverButton
                                href={`/proposals/${proposal.id}`}
                            >
                                {t('View your proposal')}
                            </InteractiveHoverButton>
                        )}
                        {project?.open && !proposal && (
                            <InteractiveHoverButton href={`/jobs/${id}/apply`}>
                                {t('Apply to this project')}
                            </InteractiveHoverButton>
                        )}
                        {project && (
                            <Link href={`/jobs/${id}`}>
                                {t('View project')}
                            </Link>
                        )}
                        <Button
                            variant="outline"
                            disabled={removing !== null}
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

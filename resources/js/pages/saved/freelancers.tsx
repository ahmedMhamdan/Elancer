import { Link, router } from '@inertiajs/react';
import type { Freelancer } from '@/components/freelancer-card';
import Button from '@/components/tailadmin/button';
import Pagination from '@/components/tailadmin/pagination';
import { useTranslation } from '@/hooks/use-translation';
import { JobDate } from '@/pages/discovery/shared';
import type { Page } from '@/pages/discovery/shared';
import { SavedLayout } from './shared';

type Save = {
    id: number;
    saved_at: string;
    // Null once the profile is no longer public; none of its details are sent then.
    freelancer: Freelancer | null;
};

export default function SavedFreelancers({ saves }: { saves: Page<Save> }) {
    const { t } = useTranslation();
    const remove = (id: number) =>
        router.delete(`/freelancers/${id}/save`, { preserveScroll: true });
    return (
        <SavedLayout
            title={t('Saved freelancers')}
            intro={t('Freelancers you saved. Only you can see this list.')}
        >
            {!saves.data.length && (
                <section className="market-panel">
                    <h3>{t('No saved freelancers yet.')}</h3>
                    <p className="market-muted">
                        {t(
                            'Save a freelancer from the directory or their profile and they appear here.',
                        )}
                    </p>
                    <Link href="/freelancers">{t('Find freelancers')}</Link>
                </section>
            )}
            {saves.data.map(({ id, saved_at, freelancer: person }) => (
                <article key={id} className="market-panel market-stack">
                    {person ? (
                        <>
                            <div className="market-actions">
                                <span className="talent-availability">
                                    {t(
                                        person.availability === 'busy'
                                            ? 'Busy'
                                            : 'Available for work',
                                    )}
                                </span>
                                <span className="market-muted">
                                    {t('Saved on')} <JobDate value={saved_at} />
                                </span>
                            </div>
                            <h3>
                                <Link href={`/freelancers/${id}`} dir="auto">
                                    {person.name}
                                </Link>
                            </h3>
                            <p dir="auto">{person.headline}</p>
                            <div className="job-skills">
                                {person.skills.slice(0, 6).map((skill) => (
                                    <span key={skill.id} dir="auto">
                                        {skill.name}
                                    </span>
                                ))}
                            </div>
                        </>
                    ) : (
                        <p>{t('This profile is no longer public.')}</p>
                    )}
                    <div className="market-actions">
                        {person && (
                            <>
                                <Link href={`/freelancers/${id}`}>
                                    {t('View profile')}
                                </Link>
                                <Link href={`/freelancers/${id}/invite`}>
                                    {t('Invite to a project')}
                                </Link>
                            </>
                        )}
                        <Button
                            variant="outline"
                            aria-label={
                                person
                                    ? t('Remove :name from saved items', {
                                          name: person.name,
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

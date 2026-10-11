// Q33: real open projects, in the homepage's card anatomy with one of Elancer's own drawings as the cover.
import { Link } from '@inertiajs/react';
import { ArrowRight, ArrowUpRight } from 'lucide-react';
import HomeEmpty from '@/components/home/home-empty';
import ProjectArt, { artTones, workKind } from '@/components/home/project-art';
import { useTranslation } from '@/hooks/use-translation';
import { JobDate, Money } from '@/pages/discovery/shared';
import type { Job } from '@/pages/discovery/shared';

export type HomeProject = Omit<Job, 'excerpt'>;

export default function HomeProjects({
    projects,
    total,
    destination,
}: {
    projects: HomeProject[];
    total: number;
    destination: string;
}) {
    const { t } = useTranslation();
    return (
        <section
            id="open-projects"
            className="elancer-talent-section"
            aria-labelledby="projects-heading"
        >
            <div className="elancer-section-heading">
                <div>
                    <span className="elancer-eyebrow">
                        {t('Open right now')}
                    </span>
                    <h2 id="projects-heading">
                        {t('Fresh projects, open for proposals.')}
                    </h2>
                </div>
                <div className="home-live-note">
                    <span className="home-live-count">
                        <span className="home-live-dot" aria-hidden="true" />
                        {t('Open projects: :count', { count: total })}
                    </span>
                    <p>{t('Posted by clients on Elancer, newest first.')}</p>
                </div>
            </div>
            {projects.length === 0 ? (
                <HomeEmpty
                    title={t('No open projects right now.')}
                    text={t(
                        'New projects appear here as soon as a client publishes one.',
                    )}
                    action={t('Start as a client')}
                    href={destination}
                />
            ) : (
                <>
                    <div className="elancer-talent-grid">
                        {projects.map((project) => (
                            <article
                                key={project.id}
                                className="elancer-talent-card home-project-card"
                            >
                                <div className="home-art-frame">
                                    <ProjectArt
                                        kind={workKind(
                                            project.category.categoryname,
                                        )}
                                        tone={
                                            artTones[
                                                project.id % artTones.length
                                            ]
                                        }
                                    />
                                </div>
                                <div className="elancer-talent-body">
                                    <div className="home-project-top">
                                        <Link
                                            href={`/jobs?category=${encodeURIComponent(project.category.slug)}`}
                                            className="home-project-category"
                                            dir="auto"
                                        >
                                            {project.category.categoryname}
                                        </Link>
                                        <span>
                                            {project.proposals_received === 0
                                                ? t('No proposals yet')
                                                : project.proposals_received ===
                                                    1
                                                  ? t('1 proposal received')
                                                  : t(
                                                        ':count proposals received',
                                                        {
                                                            count: project.proposals_received,
                                                        },
                                                    )}
                                        </span>
                                    </div>
                                    <h3 className="home-project-title">
                                        <Link
                                            href={`/jobs/${project.id}`}
                                            dir="auto"
                                        >
                                            {project.title}
                                        </Link>
                                    </h3>
                                    <dl className="home-project-facts">
                                        <div>
                                            <dt>{t('Fixed-price budget')}</dt>
                                            <dd>
                                                <Money
                                                    min={project.budget_min}
                                                    max={project.budget_max}
                                                />
                                            </dd>
                                        </div>
                                        <div>
                                            <dt>{t('Apply by')}</dt>
                                            <dd>
                                                <JobDate
                                                    value={
                                                        project.application_closes_at
                                                    }
                                                />
                                            </dd>
                                        </div>
                                    </dl>
                                    <ul
                                        className="elancer-skill-tags"
                                        aria-label={t('Skills')}
                                    >
                                        {project.skills
                                            .slice(0, 3)
                                            .map((skill) => (
                                                <li key={skill.id} dir="auto">
                                                    {skill.name}
                                                </li>
                                            ))}
                                        {project.skills.length > 3 && (
                                            <li>
                                                <bdi>
                                                    +{project.skills.length - 3}
                                                </bdi>
                                            </li>
                                        )}
                                    </ul>
                                    {/* The title is the card's one link to the project; this row only points at it. */}
                                    <span
                                        className="elancer-profile-trigger"
                                        aria-hidden="true"
                                    >
                                        {t('View project')}
                                        <ArrowUpRight size={16} />
                                    </span>
                                </div>
                            </article>
                        ))}
                    </div>
                    <div className="elancer-talents-more">
                        <Link href="/jobs" className="elancer-outline-button">
                            {t('Browse all projects')}
                            <ArrowRight size={16} aria-hidden="true" />
                        </Link>
                    </div>
                </>
            )}
        </section>
    );
}

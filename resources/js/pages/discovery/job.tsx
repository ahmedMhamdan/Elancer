import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';
import { DiscoveryLayout, JobDate, Money } from './shared';
import type { Job } from './shared';
export default function JobDetails({
    project,
    client,
    returnUrl,
}: {
    project: Job;
    client: {
        name: string;
        country: string | null;
        member_since: string | null;
    };
    returnUrl: string;
}) {
    const { t, locale } = useTranslation();
    return (
        <DiscoveryLayout>
            <Head title={project.title} />
            <Link href={returnUrl} preserveScroll className="job-back">
                <ArrowLeft size={18} className="rtl:rotate-180" />
                {t('Back to results')}
            </Link>
            <header className="jobs-heading">
                <div>
                    <Link
                        className="job-category"
                        href={`/jobs?category=${encodeURIComponent(project.category.slug)}`}
                        dir="auto"
                    >
                        {project.category.categoryname}
                    </Link>
                    <h1 dir="auto">{project.title}</h1>
                    <p>
                        {t('Posted')} <JobDate value={project.published_at} />
                    </p>
                </div>
                <span className={project.open ? 'job-open' : 'job-muted'}>
                    {t(
                        project.open
                            ? 'Open for applications'
                            : 'Applications closed',
                    )}
                </span>
            </header>
            <div className="job-detail-grid">
                <article className="job-card">
                    <h2>{t('Project brief')}</h2>
                    <p className="job-description" dir="auto">
                        {project.description}
                    </p>
                    <h2>{t('Required skills')}</h2>
                    <div className="job-skills">
                        {project.skills.map((s) => (
                            <span key={s.id} dir="auto">
                                {s.name}
                            </span>
                        ))}
                    </div>
                    {!!project.screening_questions?.length && (
                        <>
                            <h2>{t('Screening questions')}</h2>
                            <ol>
                                {project.screening_questions.map((q, i) => (
                                    <li key={i} dir="auto">
                                        {q}
                                    </li>
                                ))}
                            </ol>
                        </>
                    )}
                </article>
                <aside className="job-card job-detail-meta">
                    <h2>{t('Fixed-price budget')}</h2>
                    <strong>
                        <Money
                            min={project.budget_min}
                            max={project.budget_max}
                        />
                    </strong>
                    <p>
                        {t(
                            'The advertised budget is a guide. Final terms are agreed with the freelancer.',
                        )}
                    </p>
                    <hr />
                    <h2>{t('Application cutoff')}</h2>
                    <p>
                        {new Intl.DateTimeFormat(locale, {
                            dateStyle: 'long',
                            timeStyle: 'short',
                            timeZone: 'UTC',
                        }).format(new Date(project.application_closes_at))}{' '}
                        (UTC)
                    </p>
                    <p>
                        {t(
                            'This is the deadline to apply, not the delivery date.',
                        )}
                    </p>
                    <hr />
                    <h2>{t('About the client')}</h2>
                    <p dir="auto">{client.name}</p>
                    {client.country && <p dir="auto">{client.country}</p>}
                    {client.member_since && (
                        <p>
                            {t('Member since :year', {
                                year: client.member_since,
                            })}
                        </p>
                    )}
                    <hr />
                    <p>{t('Proposal submissions will be available soon.')}</p>
                </aside>
            </div>
        </DiscoveryLayout>
    );
}

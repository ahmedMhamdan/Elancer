import { Head, Link, usePage, router } from '@inertiajs/react';
import ReportDialog from '@/components/report-dialog';
import Button from '@/components/tailadmin/button';
import { ArrowLeft, ExternalLink, MapPin } from 'lucide-react';
import { Stars } from '@/pages/contracts/reviews';
import { DiscoveryLayout, JobDate } from './shared';
import type { PublicCase } from './portfolio-case';
import type { Freelancer } from '@/components/freelancer-card';
import { useTranslation } from '@/hooks/use-translation';
import '../../../css/elancer-marketplace.css';
export default function FreelancerProfile({
    freelancer: person,
    canContact,
    reviews,
    cases,
}: {
    freelancer: Freelancer;
    cases: PublicCase[];
    canContact: boolean;
    reviews: {
        id: number;
        rating: number;
        body: string;
        created_at: string;
        author: string;
        project_title: string | null;
    }[];
}) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    return (
        <DiscoveryLayout>
            <Head title={person.name} />
            <Link className="job-back" href="/freelancers">
                <ArrowLeft size={17} className="rtl:rotate-180" />
                {t('All freelancers')}
            </Link>
            <section className="talent-profile-header">
                <div className="talent-avatar talent-avatar-large">
                    {person.avatar ? (
                        <img src={person.avatar} alt="" />
                    ) : (
                        <span>
                            {person.name
                                .trim()
                                .split(/\s+/)
                                .slice(0, 2)
                                .map((p) => p[0])
                                .join('')}
                        </span>
                    )}
                </div>
                <div>
                    <span className="talent-availability">
                        {t(
                            person.availability === 'busy'
                                ? 'Busy'
                                : 'Available for work',
                        )}
                    </span>
                    <h1 dir="auto">{person.name}</h1>
                    <p className="talent-headline" dir="auto">
                        {person.headline}
                    </p>
                    {person.country && (
                        <p className="talent-meta">
                            <MapPin size={16} />
                            <bdi>{person.country}</bdi>
                        </p>
                    )}
                </div>
            </section>
            {canContact && (
                <div className="market-actions mb-4">
                    <Link
                        className="job-button"
                        href={
                            auth.user
                                ? `/freelancers/${person.id}/invite`
                                : '/login'
                        }
                    >
                        {t('Invite to a project')}
                    </Link>
                    {auth.user && (
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
                                        `/freelancers/${person.id}/block`,
                                    );
                            }}
                        >
                            {t('Block account')}
                        </Button>
                    )}
                    {auth.user && (
                        <ReportDialog
                            type="profile"
                            id={person.id}
                            label={t('Report this profile')}
                        />
                    )}
                </div>
            )}
            <div className="market-columns">
                <div className="market-stack">
                    <section className="market-panel">
                        <h2>{t('About')}</h2>
                        <p className="market-prose" dir="auto">
                            {person.bio}
                        </p>
                    </section>
                    {cases.length > 0 && (
                        <section className="market-panel market-stack">
                            <h2 className="!mb-0">{t('Portfolio')}</h2>
                            {cases.map((item) => (
                                <article key={item.id}>
                                    <h3 dir="auto">
                                        <Link href={`/portfolio/${item.id}`}>
                                            {item.content.title}
                                        </Link>
                                    </h3>
                                    <p dir="auto" className="break-words">
                                        {item.content.summary}
                                    </p>
                                    {item.elancer_work && (
                                        <p className="market-muted">
                                            {t('Completed on Elancer')}
                                        </p>
                                    )}
                                </article>
                            ))}
                        </section>
                    )}
                    <section className="market-panel">
                        <h2>{t('Work and professional links')}</h2>
                        {person.links.length ? (
                            <ul className="talent-links">
                                {person.links.map((link, i) => (
                                    <li key={i}>
                                        <a
                                            href={link.url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            <span dir="auto">{link.label}</span>
                                            <ExternalLink
                                                size={16}
                                                aria-label={t(
                                                    'Opens in a new tab',
                                                )}
                                            />
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="market-muted">
                                {t('No work links shared yet.')}
                            </p>
                        )}
                    </section>
                    <section className="market-panel market-stack">
                        <h2>{t('Reviews')}</h2>
                        {reviews.length ? (
                            reviews.map((review) => (
                                <article
                                    key={review.id}
                                    className="market-stack"
                                >
                                    <div className="market-actions">
                                        <Stars rating={review.rating} />
                                        <span className="market-muted">
                                            <bdi>{review.author}</bdi>
                                            {' · '}
                                            <JobDate
                                                value={review.created_at}
                                            />
                                        </span>
                                    </div>
                                    <h3 dir="auto" className="!mb-0">
                                        {review.project_title}
                                    </h3>
                                    <p
                                        dir="auto"
                                        className="market-prose break-words"
                                    >
                                        {review.body}
                                    </p>
                                </article>
                            ))
                        ) : (
                            <p className="market-muted">
                                {t('No published reviews yet.')}
                            </p>
                        )}
                    </section>
                </div>
                <aside className="market-stack">
                    <section className="market-panel">
                        <h2>{t('Skills')}</h2>
                        <div className="job-skills">
                            {person.skills.map((skill) => (
                                <span key={skill.id} dir="auto">
                                    {skill.name}
                                </span>
                            ))}
                        </div>
                    </section>
                    <section className="market-panel">
                        <h2>{t('On Elancer')}</h2>
                        <p>
                            {t('Member since :year', {
                                year: person.member_since ?? '',
                            })}
                        </p>
                        <p className="market-muted">
                            {t('A new collaboration starts with a project.')}
                        </p>
                        <Link
                            className="talent-profile-link"
                            href="/my-projects"
                        >
                            {t('My projects')}
                        </Link>
                    </section>
                </aside>
            </div>
        </DiscoveryLayout>
    );
}

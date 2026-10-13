import { Head, Link, usePage, router } from '@inertiajs/react';
import ReportDialog from '@/components/report-dialog';
import SaveToggle from '@/components/save-toggle';
import Button from '@/components/tailadmin/button';
import { ArrowLeft, ExternalLink, MapPin } from 'lucide-react';
import RatingSummary, {
    CompletedProjects,
    ReviewList,
} from '@/components/rating-summary';
import type { PublicReview, Rating } from '@/components/rating-summary';
import { DiscoveryLayout } from './shared';
import type { PublicCase } from './portfolio-case';
import type { Freelancer } from '@/components/freelancer-card';
import { useTranslation } from '@/hooks/use-translation';
import '../../../css/elancer-marketplace.css';
export default function FreelancerProfile({
    freelancer: person,
    canContact,
    apart,
    reviews,
    record,
    cases,
    saved,
}: {
    freelancer: Freelancer;
    // Q36: the viewer's own save; never anyone else's.
    saved: boolean;
    cases: PublicCase[];
    canContact: boolean;
    // Plan 027: set when the viewer and this freelancer cannot hire each other, with the side the freelancer is on.
    apart: 'demo' | 'member' | null;
    // Q82: the newest published reviews of this freelancer's work.
    reviews: PublicReview[];
    // Completed contracts as a freelancer and the rating over every published review of that work.
    record: { completed: number; rating: Rating };
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
                    {(person.country || record.rating.count > 0) && (
                        <p className="talent-meta">
                            {person.country && (
                                <span>
                                    <MapPin size={16} aria-hidden="true" />
                                    <bdi>{person.country}</bdi>
                                </span>
                            )}
                            <RatingSummary rating={record.rating} />
                        </p>
                    )}
                </div>
            </section>
            {canContact && (
                <div className="market-actions mb-4">
                    {apart ? (
                        <p className="job-muted" data-test="demo-apart">
                            {apart === 'demo'
                                ? t(
                                      'This is a sample profile from the demo, so member accounts cannot invite it.',
                                  )
                                : t(
                                      'This freelancer is a member. A demo account can invite demo freelancers only.',
                                  )}
                        </p>
                    ) : (
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
                    )}
                    {auth.user && (
                        <SaveToggle
                            url={`/freelancers/${person.id}/save`}
                            saved={saved}
                            name={person.name}
                            only={['saved']}
                        />
                    )}
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
                        <div className="market-actions">
                            <h2 className="!mb-0">{t('Reviews')}</h2>
                            <RatingSummary rating={record.rating} />
                        </div>
                        {reviews.length ? (
                            <div>
                                <ReviewList
                                    reviews={reviews}
                                    total={record.rating.count}
                                />
                            </div>
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
                        <p>
                            <CompletedProjects count={record.completed} />
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

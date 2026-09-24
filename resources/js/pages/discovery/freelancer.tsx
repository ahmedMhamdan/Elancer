import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ExternalLink, MapPin } from 'lucide-react';
import { DiscoveryLayout } from './shared';
import type { Freelancer } from '@/components/freelancer-card';
import { useTranslation } from '@/hooks/use-translation';
import '../../../css/elancer-marketplace.css';
export default function FreelancerProfile({
    freelancer: person,
}: {
    freelancer: Freelancer;
}) {
    const { t } = useTranslation();
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
            <div className="market-columns">
                <div className="market-stack">
                    <section className="market-panel">
                        <h2>{t('About')}</h2>
                        <p className="market-prose" dir="auto">
                            {person.bio}
                        </p>
                    </section>
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

// Q33: real public freelancer profiles, in the homepage's card anatomy.
import { Link } from '@inertiajs/react';
import { ArrowRight, ArrowUpRight, MapPin } from 'lucide-react';
import type { Freelancer } from '@/components/freelancer-card';
import HomeEmpty from '@/components/home/home-empty';
import { Monogram } from '@/components/home/project-art';
import RatingSummary from '@/components/rating-summary';
import { useTranslation } from '@/hooks/use-translation';

export type HomeFreelancer = Omit<Freelancer, 'links'>;

export default function HomeTalent({
    freelancers,
    destination,
}: {
    freelancers: HomeFreelancer[];
    destination: string;
}) {
    const { t } = useTranslation();
    return (
        <section
            id="featured-freelancers"
            className="elancer-talent-section"
            aria-labelledby="talent-heading"
        >
            <div className="elancer-section-heading">
                <div>
                    <span className="elancer-eyebrow">
                        {t('Freelancers on Elancer')}
                    </span>
                    <h2 id="talent-heading">
                        {t('Meet the skills behind the work.')}
                    </h2>
                </div>
                <div className="home-live-note">
                    <p>{t('Profiles published by freelancers on Elancer.')}</p>
                </div>
            </div>
            {freelancers.length === 0 ? (
                <HomeEmpty
                    title={t('No public freelancer profiles yet.')}
                    text={t(
                        'Profiles appear here once a freelancer publishes theirs.',
                    )}
                    action={t('Start as a freelancer')}
                    href={destination}
                />
            ) : (
                <>
                    <div className="elancer-talent-grid">
                        {freelancers.map((person) => (
                            <article
                                key={person.id}
                                className="elancer-talent-card home-person-card"
                            >
                                <div className="elancer-talent-body">
                                    <div className="elancer-talent-identity">
                                        <span className="home-person-photo">
                                            {person.avatar ? (
                                                <img
                                                    src={person.avatar}
                                                    alt=""
                                                    loading="lazy"
                                                    decoding="async"
                                                />
                                            ) : (
                                                <Monogram
                                                    name={person.name}
                                                    seed={person.id}
                                                />
                                            )}
                                        </span>
                                        <div className="min-w-0">
                                            <h3 className="home-person-name">
                                                <Link
                                                    href={`/freelancers/${person.id}`}
                                                    dir="auto"
                                                >
                                                    {person.name}
                                                </Link>
                                            </h3>
                                            <p dir="auto">{person.headline}</p>
                                        </div>
                                    </div>
                                    <p className="home-person-meta">
                                        <span
                                            className="home-availability"
                                            data-state={person.availability}
                                        >
                                            {t(
                                                person.availability === 'busy'
                                                    ? 'Busy'
                                                    : 'Available for work',
                                            )}
                                        </span>
                                        {person.country && (
                                            <span>
                                                <MapPin
                                                    size={14}
                                                    aria-hidden="true"
                                                />
                                                <bdi>{person.country}</bdi>
                                            </span>
                                        )}
                                        {person.rating && (
                                            <RatingSummary
                                                rating={person.rating}
                                            />
                                        )}
                                    </p>
                                    <p
                                        className="elancer-talent-intro"
                                        dir="auto"
                                    >
                                        {person.bio}
                                    </p>
                                    <ul
                                        className="elancer-skill-tags"
                                        aria-label={t('Skills: :name', {
                                            name: person.name,
                                        })}
                                    >
                                        {person.skills
                                            .slice(0, 3)
                                            .map((skill) => (
                                                <li key={skill.id} dir="auto">
                                                    {skill.name}
                                                </li>
                                            ))}
                                        {person.skills.length > 3 && (
                                            <li>
                                                <bdi>
                                                    +{person.skills.length - 3}
                                                </bdi>
                                            </li>
                                        )}
                                    </ul>
                                    {/* The name is the card's one link to the profile; this row only points at it. */}
                                    <span
                                        className="elancer-profile-trigger"
                                        aria-hidden="true"
                                    >
                                        {t('View profile')}
                                        <ArrowUpRight size={16} />
                                    </span>
                                </div>
                            </article>
                        ))}
                    </div>
                    <div className="elancer-talents-more">
                        <Link
                            href="/freelancers"
                            className="elancer-outline-button"
                        >
                            {t('Browse all freelancers')}
                            <ArrowRight size={16} aria-hidden="true" />
                        </Link>
                    </div>
                </>
            )}
        </section>
    );
}

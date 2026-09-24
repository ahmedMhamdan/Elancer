// Adapted from local TailAdmin UserProfile/UserMetaCard.tsx (MIT).
// Retains avatar/name/headline grouping; Elancer tokens, RTL and real member data replace demos.
import { Link } from '@inertiajs/react';
import { MapPin } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';
export type WorkLink = { label: string; url: string };
export type Freelancer = {
    id: number;
    name: string;
    headline: string;
    bio: string;
    country: string | null;
    availability: string;
    avatar: string | null;
    member_since: string | null;
    skills: { id: number; name: string }[];
    links: WorkLink[];
};
export default function FreelancerCard({
    person,
    compact = false,
}: {
    person: Freelancer;
    compact?: boolean;
}) {
    const { t } = useTranslation();
    return (
        <article className="talent-card">
            <div className="talent-person">
                <div className="talent-avatar">
                    {person.avatar ? (
                        <img src={person.avatar} alt="" loading="lazy" />
                    ) : (
                        <span aria-hidden="true">
                            {person.name
                                .trim()
                                .split(/\s+/)
                                .slice(0, 2)
                                .map((p) => p[0])
                                .join('')}
                        </span>
                    )}
                </div>
                <div className="min-w-0">
                    <h2>
                        <Link href={`/freelancers/${person.id}`} dir="auto">
                            {person.name}
                        </Link>
                    </h2>
                    <p dir="auto">{person.headline}</p>
                </div>
            </div>
            <div className="talent-meta">
                <span className="talent-availability">
                    {t(
                        person.availability === 'busy'
                            ? 'Busy'
                            : 'Available for work',
                    )}
                </span>
                {person.country && (
                    <span>
                        <MapPin size={14} aria-hidden="true" />
                        <bdi>{person.country}</bdi>
                    </span>
                )}
            </div>
            {!compact && (
                <p className="talent-excerpt" dir="auto">
                    {person.bio}
                </p>
            )}
            <div className="job-skills">
                {person.skills.slice(0, 6).map((skill) => (
                    <span key={skill.id} dir="auto">
                        {skill.name}
                    </span>
                ))}
            </div>
            <Link
                className="talent-profile-link"
                href={`/freelancers/${person.id}`}
            >
                {t('View profile')}
            </Link>
        </article>
    );
}

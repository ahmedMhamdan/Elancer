import { useTranslation } from '@/hooks/use-translation';
import IdentityVerification, {
    type IdentityStatus,
} from '@/components/identity-verification';
import { Head, usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    Globe2,
    MapPin,
    ShieldCheck,
    UserRound,
} from 'lucide-react';
import ComponentCard from '@/components/component-card';
import ProfileForm from '@/components/profile-form';
import { dashboard } from '@/routes';

export type MarketplaceProfile = {
    headline: string | null;
    bio: string | null;
    location: string | null;
    published_at: string | null;
    country: string | null;
    city: string | null;
    company: string | null;
    skills: string[] | null;
};

export default function MarketplaceProfile({
    profile,
    identity,
}: {
    profile: MarketplaceProfile | null;
    identity: IdentityStatus;
}) {
    const { t } = useTranslation();

    const page = usePage();
    const { auth } = page.props;
    const name = auth.user.name;
    const isClient = auth.user.workspace_role === 'client';
    const total = isClient ? 2 : 3;
    const completed = [
        ...(isClient ? [] : [profile?.headline]),
        profile?.bio,
        profile?.location,
    ].filter((value) => value?.trim()).length;
    const percent = Math.round((completed / total) * 100);
    const initials = name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0])
        .join('');
    const published = Boolean(profile?.published_at);

    return (
        <>
            <Head title={t('My profile')} />
            <div className="workspace-dashboard workspace-dashboard-clean">
                <div className="workspace-page-heading">
                    <div>
                        <h1>{t('My profile')}</h1>
                        <p>
                            {t(
                                isClient
                                    ? 'Welcome back, :name. Manage your client profile.'
                                    : 'Welcome back, :name. Manage your freelancer profile.',
                                { name: name.trim().split(/\s+/)[0] },
                            )}
                        </p>
                    </div>
                </div>
                <div
                    className="workspace-summary"
                    aria-label={t('Profile overview')}
                >
                    <section className="workspace-metric">
                        <span className="workspace-icon">
                            <UserRound aria-hidden="true" />
                        </span>
                        <div>
                            <p>{t('Profile essentials')}</p>
                            <strong>
                                {t(':completed / :total complete', {
                                    completed,
                                    total,
                                })}
                            </strong>
                        </div>
                        <span className="workspace-metric-note">
                            {percent}%
                        </span>
                    </section>
                    <section className="workspace-metric">
                        <span className="workspace-icon">
                            <Globe2 aria-hidden="true" />
                        </span>
                        <div>
                            <p>{t('Profile visibility')}</p>
                            <strong>
                                {published ? t('Published') : t('Draft')}
                            </strong>
                        </div>
                    </section>
                    <section className="workspace-metric">
                        <span className="workspace-icon">
                            <ShieldCheck aria-hidden="true" />
                        </span>
                        <div>
                            <p>{t('Email verification')}</p>
                            <strong>
                                {auth.user.email_verified_at
                                    ? t('Verified')
                                    : t('Pending')}
                            </strong>
                        </div>
                        {auth.user.email_verified_at && (
                            <CheckCircle2
                                size={19}
                                className="workspace-accent"
                                aria-hidden="true"
                            />
                        )}
                    </section>
                </div>
                <div className="workspace-columns">
                    <div className="min-w-0 space-y-6">
                        <ProfileForm profile={profile} />
                        <IdentityVerification identity={identity} />
                    </div>
                    <aside>
                        <ComponentCard
                            title={t('Your profile')}
                            desc={t('Your latest saved details.')}
                        >
                            <div className="profile-summary-person">
                                <div
                                    className="profile-summary-avatar"
                                    aria-hidden="true"
                                >
                                    {auth.user.avatar ? (
                                        <img
                                            src={auth.user.avatar}
                                            alt=""
                                            className="h-full w-full rounded-full object-cover"
                                        />
                                    ) : (
                                        initials
                                    )}
                                </div>
                                <div>
                                    <h3 dir="auto">{name}</h3>
                                    <span className="workspace-badge">
                                        {published
                                            ? t('Published')
                                            : t('Draft')}
                                    </span>
                                </div>
                            </div>
                            <dl className="profile-summary-details">
                                <div>
                                    <dt>
                                        {isClient
                                            ? t('Company')
                                            : t('Headline')}
                                    </dt>
                                    <dd dir="auto">
                                        {(isClient
                                            ? profile?.company
                                            : profile?.headline) ||
                                            t('Not added yet')}
                                    </dd>
                                </div>
                                <div>
                                    <dt>{t('Location')}</dt>
                                    <dd className="flex items-start gap-2">
                                        <MapPin
                                            size={16}
                                            className="shrink-0"
                                            aria-hidden="true"
                                        />
                                        {profile?.location ||
                                            t('Not added yet')}
                                    </dd>
                                </div>
                                <div>
                                    <dt>{t('Bio')}</dt>
                                    <dd dir="auto">
                                        {profile?.bio ||
                                            t(
                                                'Add a short introduction to help clients get to know you.',
                                            )}
                                    </dd>
                                </div>
                                {!isClient &&
                                    Boolean(profile?.skills?.length) && (
                                        <div>
                                            <dt>{t('Skills')}</dt>
                                            <dd dir="auto">
                                                {profile?.skills?.map(
                                                    (skill) => (
                                                        <span
                                                            key={skill}
                                                            className="bg-primary/10 me-2 mb-2 inline-block rounded-full px-3 py-1 text-sm"
                                                        >
                                                            {skill}
                                                        </span>
                                                    ),
                                                )}
                                            </dd>
                                        </div>
                                    )}
                            </dl>
                            <div className="profile-summary-progress">
                                <div>
                                    <span>{t('Profile completion')}</span>
                                    <strong>{percent}%</strong>
                                </div>
                                <progress
                                    value={completed}
                                    max={total}
                                    aria-label={t('Saved profile essentials')}
                                />
                            </div>
                        </ComponentCard>
                    </aside>
                </div>
            </div>
        </>
    );
}

MarketplaceProfile.layout = {
    breadcrumbs: [
        { title: 'Workspace', href: dashboard() },
        { title: 'My profile', href: '/my-profile' },
    ],
};

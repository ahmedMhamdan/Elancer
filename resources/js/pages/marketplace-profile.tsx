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
            <Head title="My profile" />
            <div className="workspace-dashboard workspace-dashboard-clean">
                <div className="workspace-page-heading">
                    <div>
                        <h1>My profile</h1>
                        <p>
                            Welcome back, {name.trim().split(/\s+/)[0]}. Manage
                            your {isClient ? 'client' : 'freelancer'} profile.
                        </p>
                    </div>
                </div>
                <div
                    className="workspace-summary"
                    aria-label="Profile overview"
                >
                    <section className="workspace-metric">
                        <span className="workspace-icon">
                            <UserRound aria-hidden="true" />
                        </span>
                        <div>
                            <p>Profile essentials</p>
                            <strong>
                                {completed}
                                <span> / {total} complete</span>
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
                            <p>Profile visibility</p>
                            <strong>{published ? 'Published' : 'Draft'}</strong>
                        </div>
                    </section>
                    <section className="workspace-metric">
                        <span className="workspace-icon">
                            <ShieldCheck aria-hidden="true" />
                        </span>
                        <div>
                            <p>Email verification</p>
                            <strong>
                                {auth.user.email_verified_at
                                    ? 'Verified'
                                    : 'Pending'}
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
                            title="Your profile"
                            desc="Your latest saved details."
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
                                    <h3>{name}</h3>
                                    <span className="workspace-badge">
                                        {published ? 'Published' : 'Draft'}
                                    </span>
                                </div>
                            </div>
                            <dl className="profile-summary-details">
                                <div>
                                    <dt>{isClient ? 'Company' : 'Headline'}</dt>
                                    <dd>
                                        {(isClient
                                            ? profile?.company
                                            : profile?.headline) ||
                                            'Not added yet'}
                                    </dd>
                                </div>
                                <div>
                                    <dt>Location</dt>
                                    <dd className="flex items-start gap-2">
                                        <MapPin
                                            size={16}
                                            className="shrink-0"
                                            aria-hidden="true"
                                        />
                                        {profile?.location || 'Not added yet'}
                                    </dd>
                                </div>
                                <div>
                                    <dt>Bio</dt>
                                    <dd>
                                        {profile?.bio ||
                                            'Add a short introduction to help clients get to know you.'}
                                    </dd>
                                </div>
                                {!isClient &&
                                    Boolean(profile?.skills?.length) && (
                                        <div>
                                            <dt>Skills</dt>
                                            <dd>
                                                {profile?.skills?.join(', ')}
                                            </dd>
                                        </div>
                                    )}
                            </dl>
                            <div className="profile-summary-progress">
                                <div>
                                    <span>Profile completion</span>
                                    <strong>{percent}%</strong>
                                </div>
                                <progress
                                    value={completed}
                                    max={total}
                                    aria-label="Saved profile essentials"
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

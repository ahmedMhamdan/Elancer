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
};

export default function Dashboard({
    profile,
}: {
    profile: MarketplaceProfile | null;
}) {
    const { auth } = usePage().props;
    const name = auth.user.name;
    const completed = [
        profile?.headline,
        profile?.bio,
        profile?.location,
    ].filter((value) => value?.trim()).length;
    const percent = Math.round((completed / 3) * 100);
    const initials = name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0])
        .join('');
    const published = Boolean(profile?.published_at);

    return (
        <>
            <Head title="Your workspace" />
            <div className="workspace-dashboard workspace-dashboard-clean">
                <div className="workspace-page-heading">
                    <div>
                        <h1>Dashboard</h1>
                        <p>
                            Welcome back, {name.trim().split(/\s+/)[0]}. Manage
                            your Elancer profile.
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
                                <span> / 3 complete</span>
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
                    <ProfileForm profile={profile} />
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
                                    {initials}
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
                                    <dt>Headline</dt>
                                    <dd>
                                        {profile?.headline || 'Not added yet'}
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
                            </dl>
                            <div className="profile-summary-progress">
                                <div>
                                    <span>Profile completion</span>
                                    <strong>{percent}%</strong>
                                </div>
                                <progress
                                    value={completed}
                                    max={3}
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

Dashboard.layout = { breadcrumbs: [{ title: 'Workspace', href: dashboard() }] };

// Uses the existing TailAdmin ComponentCard and adapted dashboard metric structure.
import { Head, Link, usePage } from '@inertiajs/react';
import {
    ShieldCheck,
    UserRound,
    ArrowUpRight,
    CheckCircle2,
} from 'lucide-react';
import ComponentCard from '@/components/component-card';
import { dashboard } from '@/routes';
import type { MarketplaceProfile } from './marketplace-profile';
export type { MarketplaceProfile } from './marketplace-profile';

export default function Dashboard({
    profile,
}: {
    profile: MarketplaceProfile | null;
}) {
    const { auth } = usePage().props;
    const client = auth.user.workspace_role === 'client';
    const ready =
        !!profile?.bio &&
        !!profile?.location &&
        (client || !!profile?.headline);
    const tasks = [
        {
            title: 'Build your profile',
            description:
                'Tell people about your experience, skills and location.',
            href: '/my-profile',
            done: ready,
        },
        {
            title: 'Secure your account',
            description:
                'Manage your password, passkeys and two-factor authentication.',
            href: '/settings/security',
            done: !!auth.user.two_factor_enabled,
        },
        {
            title: 'Check your account details',
            description: 'Keep your name and email address up to date.',
            href: '/settings/profile',
            done: !!auth.user.email_verified_at,
        },
    ];
    return (
        <div className="workspace-dashboard space-y-6">
            <Head title="Overview" />
            <div className="workspace-page-heading">
                <h1>Overview</h1>
                <p>
                    Welcome back, {auth.user.name.split(' ')[0]}. Here is your
                    workspace at a glance.
                </p>
            </div>
            <div className="workspace-summary">
                <section className="workspace-metric">
                    <span className="workspace-icon">
                        <UserRound aria-hidden="true" />
                    </span>
                    <div>
                        <p>Your workspace</p>
                        <strong>{client ? 'Client' : 'Freelancer'}</strong>
                    </div>
                </section>
                <section className="workspace-metric">
                    <span className="workspace-icon">
                        <CheckCircle2 aria-hidden="true" />
                    </span>
                    <div>
                        <p>Profile status</p>
                        <strong>
                            {profile?.published_at ? 'Published' : 'Draft'}
                        </strong>
                    </div>
                </section>
                <section className="workspace-metric">
                    <span className="workspace-icon">
                        <ShieldCheck aria-hidden="true" />
                    </span>
                    <div>
                        <p>Account security</p>
                        <strong>
                            {auth.user.two_factor_enabled
                                ? '2FA enabled'
                                : 'Set up 2FA'}
                        </strong>
                    </div>
                </section>
            </div>
            <ComponentCard
                title="Your next steps"
                desc="Keep your account ready for work."
            >
                <div className="divide-border divide-y">
                    {tasks.map((task) => (
                        <Link
                            key={task.href}
                            href={task.href}
                            className="hover:bg-muted focus-visible:outline-ring flex min-h-20 items-center justify-between gap-4 rounded-lg px-2 py-5 focus-visible:outline-2"
                        >
                            <div>
                                <h3 className="font-medium">{task.title}</h3>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    {task.description}
                                </p>
                            </div>
                            <span className="flex shrink-0 items-center gap-3">
                                {task.done && (
                                    <span className="text-primary text-xs">
                                        Ready
                                    </span>
                                )}
                                <ArrowUpRight size={20} aria-hidden="true" />
                            </span>
                        </Link>
                    ))}
                </div>
            </ComponentCard>
            {(auth.user.is_admin === true ||
                auth.user.is_super_admin === true) && (
                <ComponentCard
                    title="Administration"
                    desc="Manage your assigned responsibilities."
                >
                    <div className="flex flex-wrap gap-4">
                        <Link
                            href="/admin/categories"
                            className="text-primary min-h-11 rounded-lg px-4 py-3 underline"
                        >
                            Manage categories
                        </Link>
                        {auth.user.is_super_admin === true && (
                            <Link
                                href="/admin/administrators"
                                className="text-primary min-h-11 rounded-lg px-4 py-3 underline"
                            >
                                Manage admin access
                            </Link>
                        )}
                    </div>
                </ComponentCard>
            )}
        </div>
    );
}
Dashboard.layout = { breadcrumbs: [{ title: 'Overview', href: dashboard() }] };

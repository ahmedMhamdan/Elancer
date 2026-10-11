import { Head, Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/hooks/use-translation';
import '../../../css/elancer-marketplace.css';
import '../../../css/elancer-jobs.css';

// Q08, Q17, Q36: one private destination with three views, in the workspace list style of Invitations.
export function SavedLayout({
    title,
    intro,
    children,
}: {
    title: string;
    intro: string;
    children: ReactNode;
}) {
    const { t } = useTranslation();
    const path = usePage().url.split('?')[0];
    const views = [
        { href: '/saved/projects', label: t('Saved projects') },
        { href: '/saved/searches', label: t('Saved searches') },
        { href: '/saved/freelancers', label: t('Saved freelancers') },
    ];
    return (
        <AppLayout breadcrumbs={[{ title: t('Saved items'), href: '/saved' }]}>
            <Head title={title} />
            <main className="workspace-dashboard market-workspace market-stack">
                <h1 className="text-2xl font-semibold">{t('Saved items')}</h1>
                <nav
                    className="market-actions saved-nav"
                    aria-label={t('Saved items')}
                >
                    {views.map((view) => (
                        <Link
                            key={view.href}
                            href={view.href}
                            aria-current={
                                path === view.href ? 'page' : undefined
                            }
                        >
                            {view.label}
                        </Link>
                    ))}
                </nav>
                <h2>{title}</h2>
                <p className="market-muted">{intro}</p>
                {children}
            </main>
        </AppLayout>
    );
}

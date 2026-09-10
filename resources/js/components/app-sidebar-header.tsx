import { Link } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import ThemeToggle from '@/components/theme-toggle';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { home } from '@/routes';
import type { BreadcrumbItem } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItem[];
}) {
    return (
        <header className="workspace-header">
            <div className="flex min-w-0 items-center gap-3">
                <SidebarTrigger className="border-border size-11 rounded-full border" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div className="flex shrink-0 items-center gap-3">
                <Link
                    href={home()}
                    className="workspace-site-link hidden sm:inline-flex"
                >
                    Explore Elancer{' '}
                    <ArrowUpRight
                        size={16}
                        strokeWidth={1.75}
                        aria-hidden="true"
                    />
                </Link>
                <ThemeToggle />
            </div>
        </header>
    );
}

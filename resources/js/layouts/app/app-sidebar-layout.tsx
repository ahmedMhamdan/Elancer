import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import DemoBanner from '@/components/demo-banner';
import SuspensionBanner from '@/components/suspension-banner';
import type { AppLayoutProps } from '@/types';
import { usePage } from '@inertiajs/react';
import { useTranslation } from '@/hooks/use-translation';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const { props, component } = usePage();
    const { t } = useTranslation();
    const adminPreview = props.auth.demoAdmin && component.startsWith('admin/');
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent
                variant="sidebar"
                className="workspace-content min-w-0 overflow-x-clip"
            >
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <DemoBanner />
                <SuspensionBanner />
                {adminPreview ? (
                    <fieldset disabled className="min-w-0">
                        <legend className="sr-only">
                            {t('Administrator demo')}
                        </legend>
                        {children}
                    </fieldset>
                ) : (
                    children
                )}
            </AppContent>
        </AppShell>
    );
}

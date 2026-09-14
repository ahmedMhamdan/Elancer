import '@/../css/elancer-locale.css';
import { createInertiaApp, router } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'onboarding':
            case name === 'welcome':
            case name === 'auth/register':
            case name === 'auth/login':
            case name === 'auth/verify-email':
            case name === 'auth/confirm-password':
            case name === 'auth/two-factor-challenge':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// Keep document language/direction synchronized after Inertia visits as well as reloads.
const syncDocumentLocale = (locale: unknown) => {
    document.documentElement.lang = locale === 'ar' ? 'ar' : 'en';
    document.documentElement.dir = locale === 'ar' ? 'rtl' : 'ltr';
};
router.on('navigate', (event) =>
    syncDocumentLocale(event.detail.page.props.locale),
);
router.on('success', (event) =>
    syncDocumentLocale(event.detail.page.props.locale),
);

// This will set light / dark mode on load...
initializeTheme();

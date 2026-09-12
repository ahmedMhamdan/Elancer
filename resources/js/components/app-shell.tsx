import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Sidebar } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

type Props = { children: ReactNode; variant?: AppVariant };
export function AppShell({ children, variant = 'sidebar' }: Props) {
    const { auth } = usePage().props;
    if (variant === 'header')
        return (
            <div className="flex min-h-screen w-full flex-col">{children}</div>
        );
    return (
        <Sidebar dir={auth.user.locale === 'ar' ? 'rtl' : 'ltr'}>
            <div
                dir={auth.user.locale === 'ar' ? 'rtl' : 'ltr'}
                className="workspace-shell flex min-h-dvh w-full"
            >
                {children}
            </div>
        </Sidebar>
    );
}

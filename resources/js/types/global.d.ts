import type { RealtimeConfig } from '@/lib/realtime';
import type { Auth } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            locale: 'en' | 'ar';
            auth: Auth;
            notifications: { unread: number };
            realtime: RealtimeConfig | null;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}

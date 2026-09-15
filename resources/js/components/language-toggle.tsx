// Adapts local TailAdmin src/components/common/ThemeToggleButton.tsx (MIT).
// Reuses Elancer's theme-button styling and native button behavior with AR/EN SVG paths.
import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from '@/hooks/use-translation';

export default function LanguageToggle() {
    const { ar } = useTranslation();
    const [pending, setPending] = useState(false);
    return (
        <button
            type="button"
            className="site-theme-toggle language-toggle"
            lang={ar ? 'en' : 'ar'}
            aria-label={ar ? 'Switch to English' : 'التبديل إلى العربية'}
            title={ar ? 'Switch to English' : 'التبديل إلى العربية'}
            aria-busy={pending}
            disabled={pending}
            onClick={() =>
                router.post(
                    '/locale',
                    { locale: ar ? 'en' : 'ar' },
                    {
                        preserveScroll: true,
                        preserveState: true,
                        onStart: () => setPending(true),
                        onFinish: () => setPending(false),
                    },
                )
            }
        >
            <svg
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="1.6"
                strokeLinecap="round"
                strokeLinejoin="round"
                aria-hidden="true"
                focusable="false"
            >
                {ar ? (
                    <path d="M3 7h7M3 7v10h7M3 12h6M14 17V7l7 10V7" />
                ) : (
                    <path d="m2 17 4-10 4 10M3.6 13h4.8M14 17V7h3.5a2.5 2.5 0 0 1 0 5H14m3.5 0 4 5" />
                )}
            </svg>
        </button>
    );
}

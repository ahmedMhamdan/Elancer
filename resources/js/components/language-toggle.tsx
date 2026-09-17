// Adapts local TailAdmin src/components/common/ThemeToggleButton.tsx (MIT).
// Reuses Elancer's theme-button styling and native button behavior with AR/EN SVG paths.
import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { useTranslation } from '@/hooks/use-translation';

export default function LanguageToggle() {
    const page = usePage();
    const { ar, t } = useTranslation();
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
            onClick={async () => {
                setPending(true);
                try {
                    const token = decodeURIComponent(
                        document.cookie
                            .split('; ')
                            .find((c) => c.startsWith('XSRF-TOKEN='))
                            ?.slice(11) ?? '',
                    );
                    const response = await fetch('/locale', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-XSRF-TOKEN': token,
                        },
                        body: JSON.stringify({ locale: ar ? 'en' : 'ar' }),
                        signal: AbortSignal.timeout(15000),
                    });
                    if (!response.ok) throw new Error('Locale save failed');
                    const { locale } = await response.json();
                    if (locale !== 'en' && locale !== 'ar')
                        throw new Error('Invalid locale');
                    const applyLocale = () =>
                        new Promise<void>((resolve) => {
                            router.replace<typeof page.props>({
                                props: (props) => ({
                                    ...props,
                                    locale,
                                    ...(props.auth.user
                                        ? {
                                              auth: {
                                                  ...props.auth,
                                                  user: {
                                                      ...props.auth.user,
                                                      locale,
                                                  },
                                              },
                                          }
                                        : {}),
                                }),
                                preserveScroll: true,
                                preserveState: true,
                                onFinish: () => {
                                    document.documentElement.lang = locale;
                                    document.documentElement.dir =
                                        locale === 'ar' ? 'rtl' : 'ltr';
                                    resolve();
                                },
                            });
                        });
                    const reduced = window.matchMedia(
                        '(prefers-reduced-motion: reduce)',
                    ).matches;
                    if (!reduced && document.startViewTransition) {
                        document.documentElement.classList.add(
                            'locale-transition',
                        );
                        try {
                            await document.startViewTransition(applyLocale)
                                .finished;
                        } finally {
                            document.documentElement.classList.remove(
                                'locale-transition',
                            );
                        }
                    } else {
                        await applyLocale();
                        if (!reduced)
                            document.documentElement.animate(
                                [{ opacity: 0.65 }, { opacity: 1 }],
                                { duration: 200, easing: 'ease-out' },
                            );
                    }
                } catch {
                    toast.error(
                        t('Language could not be changed. Please try again.'),
                    );
                } finally {
                    setPending(false);
                }
            }}
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

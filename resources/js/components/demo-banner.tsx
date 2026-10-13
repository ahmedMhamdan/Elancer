// Adapted from the local TailAdmin src/components/ui/alert/Alert.tsx info variant (MIT:
// THIRD_PARTY_NOTICES.md), as suspension-banner.tsx adapts its warning variant. Keeps the bordered
// container with icon, title, message and its actions; theme tokens and a Lucide icon replace the
// demo colours and SVG, and the actions are buttons because each one ends or changes the session.
import { router, usePage } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from '@/hooks/use-translation';

/** Plans 025 and 027: tells a visitor in a demo session what it is, lets them play the other side, and offers an account of their own. */
export default function DemoBanner() {
    const { t } = useTranslation();
    const { demo, demoActing, demoOther } = usePage().props.auth;
    const [busy, setBusy] = useState(false);
    if (!demo) return null;
    const go = (url: string) => {
        if (busy) return;
        router.post(
            url,
            {},
            {
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
            },
        );
    };
    const action =
        'focus-visible:outline-ring inline-flex min-h-11 items-center text-sm font-medium underline underline-offset-4 focus-visible:outline-2';
    return (
        <div
            role="status"
            className="border-primary/40 bg-primary/10 mx-4 mt-4 rounded-xl border p-4 md:mx-6"
        >
            <div className="flex items-start gap-3">
                <Eye
                    className="text-primary mt-0.5 size-5 shrink-0"
                    aria-hidden="true"
                />
                <div className="min-w-0 space-y-1">
                    <h2 className="text-sm font-semibold">
                        {t('You are exploring a demo account')}
                    </h2>
                    <p className="text-sm">
                        {demoActing
                            ? t(
                                  'This account is shared with other visitors and filled with sample data. Try it out: post a project, apply, message, hire and pay with the test provider. It all stays inside the demo and is reset from time to time. Files, the profile and account settings are switched off.',
                              )
                            : t(
                                  'This account is shared with other visitors and filled with sample data. You can open every page; changes are switched off.',
                              )}
                    </p>
                    <div className="flex flex-wrap gap-x-6">
                        {demoOther && (
                            <button
                                type="button"
                                aria-disabled={busy}
                                data-test="demo-switch"
                                onClick={() => go('/demo/switch')}
                                className={action}
                            >
                                {demoOther === 'client'
                                    ? t('Switch to the demo client')
                                    : t('Switch to the demo freelancer')}
                            </button>
                        )}
                        <button
                            type="button"
                            aria-disabled={busy}
                            data-test="demo-leave"
                            onClick={() => go('/demo/leave')}
                            className={action}
                        >
                            {t('Create your own account')}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

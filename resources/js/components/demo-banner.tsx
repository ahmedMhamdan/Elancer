// Adapted from the local TailAdmin src/components/ui/alert/Alert.tsx info variant (MIT:
// THIRD_PARTY_NOTICES.md), as suspension-banner.tsx adapts its warning variant. Keeps the bordered
// container with icon, title, message and one action; theme tokens and a Lucide icon replace the
// demo colours and SVG, and the action is a button because it ends the demo session.
import { router, usePage } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from '@/hooks/use-translation';

/** Plan 025: tells a visitor in a demo session what it is and how to get an account of their own. */
export default function DemoBanner() {
    const { t } = useTranslation();
    const { demo } = usePage().props.auth;
    const [leaving, setLeaving] = useState(false);
    if (!demo) return null;
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
                        {t(
                            'This account is shared with other visitors and filled with sample data. You can open every page; changes are switched off.',
                        )}
                    </p>
                    <button
                        type="button"
                        aria-disabled={leaving}
                        data-test="demo-leave"
                        onClick={() => {
                            if (leaving) return;
                            router.post(
                                '/demo/leave',
                                {},
                                {
                                    onStart: () => setLeaving(true),
                                    onFinish: () => setLeaving(false),
                                },
                            );
                        }}
                        className="focus-visible:outline-ring inline-flex min-h-11 items-center text-sm font-medium underline underline-offset-4 focus-visible:outline-2"
                    >
                        {t('Create your own account')}
                    </button>
                </div>
            </div>
        </div>
    );
}

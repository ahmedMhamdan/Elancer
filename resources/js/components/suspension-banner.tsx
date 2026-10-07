// Adapted from the local TailAdmin src/components/ui/alert/Alert.tsx warning variant (MIT:
// THIRD_PARTY_NOTICES.md). Keeps the bordered container with icon, title, message and link;
// Inertia routing, theme tokens and a Lucide icon replace the demo router and SVG.
import { Link, usePage } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';

/** Q64: tells a suspended member why, what still works and where to ask. */
export default function SuspensionBanner() {
    const { t } = useTranslation();
    const { user } = usePage().props.auth;
    if (user?.status !== 'suspended') return null;
    const reason =
        typeof user.suspension_reason === 'string'
            ? user.suspension_reason
            : '';
    return (
        <div
            role="status"
            className="mx-4 mt-4 rounded-xl border border-amber-500/50 bg-amber-500/10 p-4 text-amber-900 md:mx-6 dark:text-amber-200"
        >
            <div className="flex items-start gap-3">
                <TriangleAlert
                    className="mt-0.5 size-5 shrink-0"
                    aria-hidden="true"
                />
                <div className="min-w-0 space-y-1">
                    <h2 className="text-sm font-semibold">
                        {t('Your account is suspended')}
                    </h2>
                    {reason && (
                        <p className="text-sm wrap-anywhere">
                            {t('Reason given:')} <bdi>{reason}</bdi>
                        </p>
                    )}
                    <p className="text-sm">
                        {t(
                            'You can still open your existing contracts and their conversations, deliver and review work, and request a cancellation. You cannot post projects, apply, invite, send or accept offers, or fund a contract.',
                        )}
                    </p>
                    <Link
                        href="/learn/resources"
                        className="focus-visible:outline-ring inline-flex min-h-11 items-center text-sm font-medium underline underline-offset-4 focus-visible:outline-2"
                    >
                        {t('Help with a suspended account')}
                    </Link>
                </div>
            </div>
        </div>
    );
}

// Plan 032 part 10: what a demo session sees where a control would only be refused. The card follows the
// demo banner (an adaptation of TailAdmin's Alert info variant, MIT: THIRD_PARTY_NOTICES.md) in its
// colours and its one action; the line is the muted text the pages already use.
import { router } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { useTranslation } from '@/hooks/use-translation';

/** One muted line in place of a file picker or a few buttons. */
export function DemoOffNote({ children }: { children: ReactNode }) {
    return (
        <p className="text-muted-foreground flex items-start gap-2 text-sm">
            <Lock className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <span>{children}</span>
        </p>
    );
}

/** A card in place of a whole form, with the way out of the demo. */
export function DemoOffCard({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    const { t } = useTranslation();
    const [busy, setBusy] = useState(false);
    return (
        <section
            className="border-primary/40 bg-primary/10 rounded-xl border p-4"
            data-test="demo-off"
        >
            <div className="flex items-start gap-3">
                <Lock
                    className="text-primary mt-0.5 size-5 shrink-0"
                    aria-hidden="true"
                />
                <div className="min-w-0 space-y-1">
                    <h2 className="text-sm font-semibold">{title}</h2>
                    <p className="text-sm">{children}</p>
                    <button
                        type="button"
                        aria-disabled={busy}
                        onClick={() => {
                            if (busy) return;
                            router.post(
                                '/demo/leave',
                                {},
                                {
                                    onStart: () => setBusy(true),
                                    onFinish: () => setBusy(false),
                                },
                            );
                        }}
                        className="focus-visible:outline-ring inline-flex min-h-11 items-center text-sm font-medium underline underline-offset-4 focus-visible:outline-2"
                    >
                        {t('Create your own account')}
                    </button>
                </div>
            </div>
        </section>
    );
}

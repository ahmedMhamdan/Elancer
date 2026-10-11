// Q08/Q36 Save control, built on the TailAdmin button already adapted in components/tailadmin/button.tsx.
import { router, usePage } from '@inertiajs/react';
import { Bookmark, BookmarkCheck } from 'lucide-react';
import { useState } from 'react';
import Button from '@/components/tailadmin/button';
import { useTranslation } from '@/hooks/use-translation';

export default function SaveToggle({
    url,
    saved,
    name,
    only,
}: {
    url: string;
    saved: boolean;
    // Named in the accessible label so several controls on one page stay distinguishable.
    name: string;
    // The page data that carries the saved state, refreshed without moving the page.
    only: string[];
}) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const [busy, setBusy] = useState(false);
    // Q64: a suspended member starts nothing new; their Saved lists still let them remove a save.
    if (auth.user?.status !== 'active') return null;
    const options = {
        preserveScroll: true,
        preserveState: true,
        only,
        onStart: () => setBusy(true),
        onFinish: () => setBusy(false),
    };
    return (
        <Button
            variant="outline"
            className="save-toggle"
            disabled={busy}
            aria-pressed={saved}
            aria-label={t(
                saved ? 'Remove :name from saved items' : 'Save :name',
                { name },
            )}
            startIcon={
                saved ? (
                    <BookmarkCheck size={16} aria-hidden="true" />
                ) : (
                    <Bookmark size={16} aria-hidden="true" />
                )
            }
            onClick={() =>
                saved
                    ? router.delete(url, options)
                    : router.post(url, {}, options)
            }
        >
            {t(saved ? 'Saved' : 'Save')}
        </Button>
    );
}

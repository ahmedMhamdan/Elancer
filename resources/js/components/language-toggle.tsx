// Reuses the locally sourced TailAdmin button; see tailadmin/button.tsx.
import { router } from '@inertiajs/react';
import { useState } from 'react';
import Button from '@/components/tailadmin/button';
import { useTranslation } from '@/hooks/use-translation';

export default function LanguageToggle() {
    const { ar } = useTranslation();
    const [pending, setPending] = useState(false);
    return (
        <Button
            size="sm"
            variant="outline"
            className="shrink-0"
            lang={ar ? 'en' : 'ar'}
            aria-label={ar ? 'Switch to English' : 'التبديل إلى العربية'}
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
            {ar ? 'English' : 'العربية'}
        </Button>
    );
}

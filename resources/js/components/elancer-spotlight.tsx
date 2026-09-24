import { usePage } from '@inertiajs/react';
import { BriefcaseBusiness, LayoutGrid, Search, UserRound } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { AppleSpotlight } from '@/components/ui/apple-spotlight';
import { useTranslation } from '@/hooks/use-translation';

export default function ElancerSpotlight() {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const [open, setOpen] = useState(false);
    const trigger = useRef<HTMLButtonElement>(null);

    // Keyboard entry adapted from TailAdmin src/layout/AppHeader.tsx.
    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (
                (event.ctrlKey || event.metaKey) &&
                event.key.toLowerCase() === 'k' &&
                trigger.current?.getClientRects().length
            ) {
                event.preventDefault();
                trigger.current.click();
            }
        };
        document.addEventListener('keydown', onKeyDown);
        return () => document.removeEventListener('keydown', onKeyDown);
    }, []);

    const shortcuts = [
        { label: t('Find work'), icon: <BriefcaseBusiness />, link: '/jobs' },
        {
            label: t('Find freelancers'),
            icon: <UserRound />,
            link: '/freelancers',
        },
        { label: t('Categories'), icon: <LayoutGrid />, link: '/categories' },
        ...(auth.user
            ? [
                  {
                      label: t('Dashboard'),
                      icon: <LayoutGrid />,
                      link: '/dashboard',
                  },
              ]
            : []),
    ];

    return (
        <AppleSpotlight
            isOpen={open}
            onOpenChange={setOpen}
            shortcuts={shortcuts}
            searchResults={(query) => [
                {
                    label: t('Search projects'),
                    description: query,
                    icon: <BriefcaseBusiness />,
                    link: `/jobs?q=${encodeURIComponent(query)}`,
                },
                {
                    label: t('Search freelancers'),
                    description: query,
                    icon: <UserRound />,
                    link: `/freelancers?q=${encodeURIComponent(query)}`,
                },
            ]}
            trigger={
                <button
                    ref={trigger}
                    type="button"
                    className="site-theme-toggle"
                    aria-label={t('Search Elancer')}
                    title={t('Search Elancer')}
                    aria-keyshortcuts="Control+k Meta+k"
                >
                    <Search size={20} aria-hidden="true" />
                </button>
            }
        />
    );
}

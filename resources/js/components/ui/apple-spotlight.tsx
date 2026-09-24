'use client';

// Adapted from Ahmed's supplied Apple Spotlight. Radix provides focus trapping,
// Escape/outside dismissal and focus restoration; Elancer supplies real destinations.
import * as Dialog from '@radix-ui/react-dialog';
import { Link } from '@inertiajs/react';
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion';
import { ChevronRight, Search, X } from 'lucide-react';
import { useId, useRef, useState, type ReactNode } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

export interface SpotlightDestination {
    label: string;
    icon: ReactNode;
    link: string;
    description?: string;
}

interface AppleSpotlightProps {
    shortcuts?: SpotlightDestination[];
    searchResults?: (query: string) => SpotlightDestination[];
    isOpen?: boolean;
    handleClose?: () => void;
    trigger?: ReactNode;
    onOpenChange?: (open: boolean) => void;
}

export function AppleSpotlight({ shortcuts = [], searchResults, isOpen = true, handleClose, trigger, onOpenChange }: AppleSpotlightProps) {
    const { t, ar } = useTranslation();
    const reducedMotion = useReducedMotion();
    const [value, setValue] = useState('');
    const [hovered, setHovered] = useState<string | null>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const resultsRef = useRef<HTMLDivElement>(null);
    const id = useId();
    const query = value.trim();
    const results = query ? (searchResults?.(query) ?? shortcuts.filter(item => item.label.toLocaleLowerCase().includes(query.toLocaleLowerCase()))) : [];
    const close = () => { onOpenChange?.(false); handleClose?.(); };
    const transition = reducedMotion ? { duration: 0 } : { type: 'spring' as const, stiffness: 550, damping: 50 };

    return (
        <Dialog.Root open={isOpen} onOpenChange={open => {
            if (open) { setValue(''); setHovered(null); onOpenChange?.(true); }
            else close();
        }}>
            {trigger && <Dialog.Trigger asChild>{trigger}</Dialog.Trigger>}
            <AnimatePresence>
                {isOpen && <Dialog.Portal forceMount>
                    <Dialog.Overlay asChild forceMount>
                        <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} transition={{ duration: reducedMotion ? 0 : 0.15 }} className="fixed inset-0 z-[100] bg-black/30 backdrop-blur-sm" />
                    </Dialog.Overlay>
                    <Dialog.Content asChild forceMount onOpenAutoFocus={event => { event.preventDefault(); inputRef.current?.focus(); }} aria-describedby={`${id}-description`}>
                        <motion.div dir={ar ? 'rtl' : 'ltr'} initial={{ opacity: 0, scale: reducedMotion ? 1 : 0.96, y: reducedMotion ? 0 : -10 }} animate={{ opacity: 1, scale: 1, y: 0 }} exit={{ opacity: 0, scale: reducedMotion ? 1 : 0.96 }} transition={transition} className="fixed inset-x-4 top-[20vh] z-[101] mx-auto w-auto max-w-3xl outline-none">
                            <Dialog.Title className="sr-only">{t('Search Elancer')}</Dialog.Title>
                            <p id={`${id}-description`} className="sr-only">{t('Search projects or freelancers, or open a shortcut.')}</p>
                            <div className="flex flex-col items-stretch gap-3 md:flex-row md:items-start">
                                <motion.div layout={!reducedMotion} transition={transition} className="min-w-0 flex-1 overflow-hidden rounded-[30px] border border-[var(--el-border)] bg-[var(--el-surface)] text-[var(--el-text)] shadow-xl">
                                    <form className="flex h-16 items-center gap-3 px-5" onSubmit={event => { event.preventDefault(); resultsRef.current?.querySelector<HTMLAnchorElement>('a')?.click(); }}>
                                        <Search className="size-6 shrink-0" strokeWidth={1.5} aria-hidden="true" />
                                        <div className="relative min-w-0 flex-1">
                                            {!value && <AnimatePresence mode="wait"><motion.span key={hovered ?? 'search'} initial={{ opacity: 0, y: reducedMotion ? 0 : 5 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0 }} transition={{ duration: reducedMotion ? 0 : 0.15 }} aria-hidden="true" className="pointer-events-none absolute inset-0 flex items-center truncate text-lg text-[var(--el-muted)]">{hovered ?? t('Search')}</motion.span></AnimatePresence>}
                                            <input ref={inputRef} value={value} onChange={event => setValue(event.target.value)} onKeyDown={event => { if (event.key === 'ArrowDown' && results.length) { event.preventDefault(); resultsRef.current?.querySelector<HTMLAnchorElement>('a')?.focus(); } }} aria-label={t('Search Elancer')} maxLength={100} type="search" autoComplete="off" className="h-11 w-full min-w-0 bg-transparent text-lg outline-none focus-visible:ring-2 focus-visible:ring-[var(--el-accent)] rounded-md" />
                                        </div>
                                        <Dialog.Close className="flex size-11 shrink-0 items-center justify-center rounded-full text-[var(--el-muted)] hover:bg-[var(--el-surface-soft)] focus-visible:outline-2 focus-visible:outline-[var(--el-accent)]" aria-label={t('Close')}><X size={20} aria-hidden="true" /></Dialog.Close>
                                    </form>
                                    {query && <motion.div ref={resultsRef} initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="max-h-[45dvh] overflow-y-auto border-t border-[var(--el-border)] bg-[var(--el-surface-soft)] p-2" onKeyDown={event => {
                                        const links = Array.from(resultsRef.current?.querySelectorAll<HTMLAnchorElement>('a') ?? []);
                                        const current = links.indexOf(document.activeElement as HTMLAnchorElement);
                                        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') { event.preventDefault(); const next = current + (event.key === 'ArrowDown' ? 1 : -1); if (next < 0) inputRef.current?.focus(); else links[Math.min(next, links.length - 1)]?.focus(); }
                                    }}>
                                        {results.map(result => <Link key={result.link} href={result.link} onClick={close} className="group flex min-h-16 items-center gap-3 rounded-2xl p-3 hover:bg-[var(--el-surface)] focus-visible:bg-[var(--el-surface)] focus-visible:outline-2 focus-visible:outline-[var(--el-accent)]">
                                            <span className="shrink-0 [&_svg]:size-6" aria-hidden="true">{result.icon}</span>
                                            <span className="min-w-0 flex-1"><span className="block font-medium">{result.label}</span>{result.description && <span className="block break-words text-sm text-[var(--el-muted)]">{result.description}</span>}</span>
                                            <ChevronRight className="size-5 shrink-0 rtl:rotate-180" aria-hidden="true" />
                                        </Link>)}
                                        {!results.length && <p role="status" className="p-4 text-sm text-[var(--el-muted)]">{t('No results found.')}</p>}
                                    </motion.div>}
                                </motion.div>
                                {!query && <div className="flex justify-center gap-2 md:max-w-64 md:flex-wrap" aria-label={t('Shortcuts')}>
                                    {shortcuts.map((shortcut, index) => <motion.div key={shortcut.link} initial={{ opacity: 0, scale: reducedMotion ? 1 : 0.7 }} animate={{ opacity: 1, scale: 1 }} transition={reducedMotion ? { duration: 0 } : { ...transition, delay: index * 0.04 }}>
                                        <Link href={shortcut.link} onClick={close} onMouseEnter={() => setHovered(shortcut.label)} onMouseLeave={() => setHovered(null)} onFocus={() => setHovered(shortcut.label)} onBlur={() => setHovered(null)} aria-label={shortcut.label} title={shortcut.label} className={cn('flex size-14 items-center justify-center rounded-full border border-[var(--el-border)] bg-[var(--el-surface)] text-[var(--el-muted)] shadow-sm transition-colors hover:text-[var(--el-text)] focus-visible:outline-2 focus-visible:outline-[var(--el-accent)] md:size-16')}>
                                            <span aria-hidden="true" className="[&_svg]:size-6 [&_svg]:stroke-[1.5]">{shortcut.icon}</span>
                                        </Link>
                                    </motion.div>)}
                                </div>}
                            </div>
                        </motion.div>
                    </Dialog.Content>
                </Dialog.Portal>}
            </AnimatePresence>
        </Dialog.Root>
    );
}

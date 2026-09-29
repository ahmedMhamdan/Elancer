'use client';
// Adapted from Ahmed's supplied Spotlight. Radix retains focus trapping,
// Escape/outside dismissal and focus restoration; filters use existing catalog data.
import * as Dialog from '@radix-ui/react-dialog';
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion';
import { Search, X } from 'lucide-react';
import { useRef, type ReactNode } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import '../../../css/elancer-spotlight.css';

export interface SpotlightMode {
    id: string;
    label: string;
    icon: ReactNode;
    placeholder: string;
    description: string;
    submitLabel: string;
}
interface AppleSpotlightProps {
    modes: SpotlightMode[];
    selected: string;
    onSelect: (mode: string) => void;
    query: string;
    onQueryChange: (query: string) => void;
    onSubmit: () => void;
    children?: ReactNode;
    isOpen: boolean;
    trigger: ReactNode;
    onOpenChange: (open: boolean) => void;
    busy?: boolean;
}
export function AppleSpotlight({ modes, selected, onSelect, query, onQueryChange, onSubmit, children, isOpen, trigger, onOpenChange, busy = false }: AppleSpotlightProps) {
    const { t, ar } = useTranslation();
    const reduced = useReducedMotion();
    const input = useRef<HTMLInputElement>(null);
    const mode = modes.find(item => item.id === selected) ?? modes[0];
    return (
        <Dialog.Root open={isOpen} onOpenChange={onOpenChange}>
            <Dialog.Trigger asChild>{trigger}</Dialog.Trigger>
            <AnimatePresence>
                {isOpen && (
                    <Dialog.Portal forceMount>
                        <Dialog.Overlay asChild forceMount>
                            <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} transition={{ duration: reduced ? 0 : 0.15 }} className="fixed inset-0 z-[100] bg-black/30 backdrop-blur-sm" />
                        </Dialog.Overlay>
                        <Dialog.Content asChild forceMount onOpenAutoFocus={event => { event.preventDefault(); input.current?.focus(); }}>
                            <motion.div dir={ar ? 'rtl' : 'ltr'} initial={{ opacity: 0, y: reduced ? 0 : -8 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0 }} transition={{ duration: reduced ? 0 : 0.16 }} className="elancer-search-dialog">
                                <div className="elancer-search-heading">
                                    <Dialog.Title>{t('Search Elancer')}</Dialog.Title>
                                    <Dialog.Close className="elancer-search-close" aria-label={t('Close')}><X size={20} aria-hidden="true" /></Dialog.Close>
                                </div>
                                <div className="elancer-search-modes" role="group" aria-label={t('Search type')}>
                                    {modes.map(item => <button key={item.id} type="button" aria-pressed={selected === item.id} onClick={() => { onSelect(item.id); input.current?.focus(); }}>
                                        <span aria-hidden="true">{item.icon}</span><span>{item.label}</span>
                                    </button>)}
                                </div>
                                <Dialog.Description className="elancer-search-description">{mode.description}</Dialog.Description>
                                <form onSubmit={event => { event.preventDefault(); onSubmit(); }}>
                                    <div className="elancer-search-query">
                                        <Search size={20} aria-hidden="true" />
                                        <input ref={input} type="search" value={query} onChange={event => onQueryChange(event.target.value)} aria-label={mode.placeholder} placeholder={mode.placeholder} maxLength={100} autoComplete="off" />
                                    </div>
                                    {children}
                                    <button type="submit" disabled={busy} className="elancer-search-submit"><Search size={18} aria-hidden="true" />{mode.submitLabel}</button>
                                </form>
                            </motion.div>
                        </Dialog.Content>
                    </Dialog.Portal>
                )}
            </AnimatePresence>
        </Dialog.Root>
    );
}
'use client';

import { Link } from '@inertiajs/react';
import { Menu, X } from 'lucide-react';
import { AnimatePresence, motion, useMotionValueEvent, useReducedMotion, useScroll } from 'motion/react';
import React, { useEffect, useId, useRef, useState } from 'react';
import ElancerWordmark from '@/components/elancer-wordmark';
import { cn } from '@/lib/utils';
import { home } from '@/routes';

type NavbarProps = { children: React.ReactNode; className?: string };
type NavBodyProps = NavbarProps & { visible?: boolean };
type NavItemsProps = { items: { name: string; link: string }[]; className?: string; onItemClick?: () => void };
type MobileNavMenuProps = NavbarProps & { isOpen: boolean; onClose: () => void; id?: string };

// Adapted from the supplied resizable navbar: keep it close to the top,
// use Elancer tokens, and reserve room for the actual navigation content.
export function Navbar({ children, className }: NavbarProps) {
    const { scrollY } = useScroll();
    const [visible, setVisible] = useState(false);
    useMotionValueEvent(scrollY, 'change', (latest) => setVisible((current) => current ? latest > 72 : latest > 100));
    return (
        <header data-compact={visible} className={cn('elancer-resizable-navbar sticky inset-x-0 top-0 z-40 w-full py-1', className)}>
            {React.Children.map(children, (child) => React.isValidElement(child) ? React.cloneElement(child as React.ReactElement<{ visible?: boolean }>, { visible }) : child)}
        </header>
    );
}

export function NavBody({ children, className, visible }: NavBodyProps) {
    const reduceMotion = useReducedMotion();
    return (
        <motion.div
            initial={false}
            style={{ maxWidth: 1280 }}
            animate={{ maxWidth: visible ? 1000 : 1280, y: visible ? 4 : 0 }}
            transition={reduceMotion ? { duration: 0 } : { duration: 0.45, ease: [0.22, 1, 0.36, 1] }}
            data-compact={visible}
            className={cn('elancer-resizable-body relative mx-auto hidden w-[calc(100%-2rem)] items-center justify-between gap-6 rounded-full px-6 py-3 lg:flex', className)}
        >{children}</motion.div>
    );
}

export function NavItems({ items, className, onItemClick }: NavItemsProps) {
    const [hovered, setHovered] = useState<number | null>(null);
    const hoverId = useId();
    const reduceMotion = useReducedMotion();
    return (
        <nav aria-label="Main navigation" onMouseLeave={() => setHovered(null)} className={cn('relative flex items-center gap-1', className)}>
            {items.map((item, index) => (
                <a key={item.link} href={item.link} onMouseEnter={() => setHovered(index)} onFocus={() => setHovered(index)} onBlur={() => setHovered(null)} onClick={onItemClick} className="elancer-resizable-link relative rounded-full px-4 py-3 text-sm font-medium">
                    {hovered === index && <motion.span layoutId={reduceMotion ? undefined : hoverId} className="absolute inset-0 rounded-full bg-[var(--el-soft)]" aria-hidden="true" transition={{ duration: reduceMotion ? 0 : 0.18 }} />}
                    <span className="relative">{item.name}</span>
                </a>
            ))}
        </nav>
    );
}

export function MobileNav({ children, className, visible }: NavBodyProps) {
    const reduceMotion = useReducedMotion();
    return (
        <motion.div initial={false} data-mobile-navbar data-compact={visible}
            animate={{ y: visible ? 4 : 0, width: visible ? 'calc(100% - 2rem)' : 'calc(100% - 1rem)' }}
            transition={reduceMotion ? { duration: 0 } : { duration: 0.45, ease: [0.22, 1, 0.36, 1] }}
            className={cn('elancer-resizable-body relative mx-auto flex w-[calc(100%-1rem)] flex-col rounded-[28px] px-4 py-2 lg:hidden', className)}
        >{children}</motion.div>
    );
}

export function MobileNavHeader({ children, className }: NavbarProps) {
    return <div className={cn('flex w-full items-center justify-between gap-3', className)}>{children}</div>;
}

export function MobileNavMenu({ children, className, isOpen, onClose, id = 'elancer-mobile-navigation' }: MobileNavMenuProps) {
    const ref = useRef<HTMLDivElement>(null);
    const reduceMotion = useReducedMotion();
    useEffect(() => {
        if (!isOpen) return;
        const handleKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                onClose();
                document.querySelector<HTMLButtonElement>(`[aria-controls="${id}"]`)?.focus();
            }
        };
        const handleOutside = (event: PointerEvent) => {
            if (event.target instanceof Element && !event.target.closest('[data-mobile-navbar]')) onClose();
        };
        document.addEventListener('keydown', handleKey);
        document.addEventListener('pointerdown', handleOutside);
        return () => { document.removeEventListener('keydown', handleKey); document.removeEventListener('pointerdown', handleOutside); };
    }, [isOpen, onClose, id]);
    return (
        <AnimatePresence>
            {isOpen && <motion.div ref={ref} id={id} initial={{ opacity: 0, y: reduceMotion ? 0 : -6 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0, y: reduceMotion ? 0 : -6 }} transition={{ duration: reduceMotion ? 0 : 0.16 }} className={cn('elancer-resizable-menu absolute inset-x-0 top-[calc(100%+8px)] flex max-h-[calc(100svh-100px)] flex-col gap-2 overflow-y-auto rounded-2xl p-5 shadow-xl', className)}>{children}</motion.div>}
        </AnimatePresence>
    );
}

export function MobileNavToggle({ isOpen, onClick, controls = 'elancer-mobile-navigation' }: { isOpen: boolean; onClick: () => void; controls?: string }) {
    return <button type="button" onClick={onClick} aria-label={isOpen ? 'Close navigation' : 'Open navigation'} aria-expanded={isOpen} aria-controls={controls} className="grid size-11 cursor-pointer place-items-center rounded-full hover:bg-[var(--el-soft)]">{isOpen ? <X size={22} aria-hidden="true" /> : <Menu size={22} aria-hidden="true" />}</button>;
}

export function NavbarLogo() {
    return <Link href={home()} aria-label="Elancer home" className="elancer-resizable-logo relative shrink-0 py-2"><ElancerWordmark /></Link>;
}

export function NavbarButton({ href, as: Tag = 'a', children, className, variant = 'primary', ...props }: {
    href?: string; as?: React.ElementType; children: React.ReactNode; className?: string; variant?: 'primary' | 'secondary' | 'dark' | 'gradient';
} & (React.ComponentPropsWithoutRef<'a'> | React.ComponentPropsWithoutRef<'button'>)) {
    return <Tag href={href || undefined} className={cn('elancer-navbar-button relative inline-flex min-h-11 cursor-pointer items-center justify-center rounded-full px-5 py-2.5 text-sm font-medium whitespace-nowrap transition-colors', variant === 'secondary' ? 'elancer-navbar-button-secondary' : 'elancer-navbar-button-primary', className)} {...props}>{children}</Tag>;
}
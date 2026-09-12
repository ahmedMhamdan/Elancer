// Adapted from Ahmed's supplied Aceternity Sidebar (2026-09-12).
// Preserve provider/body/link structure and hover width animation; use Inertia,
// Elancer tokens and Radix's focus-managed mobile dialog instead of Next.js.
import { Link, router } from '@inertiajs/react';
import { motion, useReducedMotion } from 'framer-motion';
import { Menu, PanelLeftClose, PanelLeftOpen } from 'lucide-react';
import * as React from 'react';
import {
    Sheet,
    SheetContent,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useIsMobile } from '@/hooks/use-mobile';
import { cn } from '@/lib/utils';

interface SidebarContextProps {
    open: boolean;
    setOpen: React.Dispatch<React.SetStateAction<boolean>>;
    pinned: boolean;
    hovered: boolean;
    setHovered: React.Dispatch<React.SetStateAction<boolean>>;
    setFocused: React.Dispatch<React.SetStateAction<boolean>>;
    openMobile: boolean;
    setOpenMobile: React.Dispatch<React.SetStateAction<boolean>>;
    isMobile: boolean;
    animate: boolean;
    reducedMotion: boolean;
    dir: 'ltr' | 'rtl';
}
const SidebarContext = React.createContext<SidebarContextProps | undefined>(
    undefined,
);
export function useSidebar() {
    const context = React.useContext(SidebarContext);
    if (!context)
        throw new Error('useSidebar must be used within a SidebarProvider');
    return context;
}
export function SidebarProvider({
    children,
    open: openProp,
    setOpen: setOpenProp,
    animate = true,
    dir = 'ltr',
}: {
    children: React.ReactNode;
    open?: boolean;
    setOpen?: React.Dispatch<React.SetStateAction<boolean>>;
    animate?: boolean;
    dir?: 'ltr' | 'rtl';
}) {
    const [openState, setOpenState] = React.useState(false);
    const [hovered, setHovered] = React.useState(false);
    const [focused, setFocused] = React.useState(false);
    const [openMobile, setOpenMobile] = React.useState(false);
    const isMobile = useIsMobile();
    const reducedMotion = !!useReducedMotion();
    const pinned = openProp ?? openState;
    const setOpen = setOpenProp ?? setOpenState;
    React.useEffect(
        () => router.on('navigate', () => setOpenMobile(false)),
        [],
    );
    React.useEffect(() => {
        if (!isMobile) setOpenMobile(false);
        setHovered(false);
        setFocused(false);
    }, [isMobile]);
    return (
        <SidebarContext.Provider
            value={{
                open: !animate || pinned || hovered || focused,
                setOpen,
                pinned,
                hovered,
                setHovered,
                setFocused,
                openMobile,
                setOpenMobile,
                isMobile,
                animate,
                reducedMotion,
                dir,
            }}
        >
            <Sheet open={openMobile && isMobile} onOpenChange={setOpenMobile}>
                {children}
            </Sheet>
        </SidebarContext.Provider>
    );
}
export const Sidebar = SidebarProvider;
export function SidebarBody({
    children,
    className,
}: {
    children: React.ReactNode;
    className?: string;
}) {
    const { isMobile } = useSidebar();
    return isMobile ? (
        <MobileSidebar className={className}>{children}</MobileSidebar>
    ) : (
        <DesktopSidebar className={className}>{children}</DesktopSidebar>
    );
}
export function DesktopSidebar({
    className,
    children,
}: {
    className?: string;
    children: React.ReactNode;
}) {
    const { open, setHovered, setFocused, reducedMotion, dir } = useSidebar();
    return (
        <motion.aside
            id="dashboard-sidebar"
            dir={dir}
            data-expanded={open}
            initial={false}
            animate={{ width: open ? 300 : 60 }}
            transition={{
                duration: reducedMotion ? 0 : 0.2,
                ease: 'easeInOut',
            }}
            onMouseEnter={() => setHovered(true)}
            onMouseLeave={() => setHovered(false)}
            onFocusCapture={() => setFocused(true)}
            onBlurCapture={(event) => {
                if (!event.currentTarget.contains(event.relatedTarget))
                    setFocused(false);
            }}
            className={cn(
                'border-sidebar-border bg-sidebar text-sidebar-foreground sticky top-0 hidden h-dvh shrink-0 flex-col overflow-hidden border-e px-2 py-5 md:flex',
                className,
            )}
        >
            {children}
        </motion.aside>
    );
}
export function MobileSidebar({
    className,
    children,
}: {
    className?: string;
    children: React.ReactNode;
}) {
    const { dir } = useSidebar();
    return (
        <SheetContent
            id="dashboard-sidebar"
            dir={dir}
            side={dir === 'rtl' ? 'right' : 'left'}
            aria-describedby={undefined}
            className={cn(
                'bg-sidebar text-sidebar-foreground w-full max-w-none gap-0 px-5 pt-16 pb-6 motion-reduce:animate-none sm:max-w-none [&>button]:grid [&>button]:size-11 [&>button]:place-items-center',
                className,
            )}
        >
            <SheetTitle className="sr-only">
                {dir === 'rtl' ? 'التنقل الرئيسي' : 'Main navigation'}
            </SheetTitle>
            {children}
        </SheetContent>
    );
}
export function SidebarLabel({ children }: { children: React.ReactNode }) {
    const { open, isMobile, reducedMotion } = useSidebar();
    return (
        <motion.span
            aria-hidden="true"
            initial={false}
            animate={{ opacity: open || isMobile ? 1 : 0 }}
            transition={{ duration: reducedMotion ? 0 : 0.15 }}
            className="min-w-0 truncate text-sm"
        >
            {children}
        </motion.span>
    );
}
export function SidebarLink({
    link,
    className,
    onClick,
    ...props
}: Omit<React.ComponentProps<typeof Link>, 'href' | 'children'> & {
    link: {
        label: string;
        href: React.ComponentProps<typeof Link>['href'];
        icon: React.ReactNode;
    };
}) {
    const { setOpenMobile, open, isMobile } = useSidebar();
    return (
        <Link
            {...props}
            href={link.href}
            aria-label={link.label}
            title={!open && !isMobile ? link.label : undefined}
            onClick={(event) => {
                onClick?.(event);
                if (!event.defaultPrevented) setOpenMobile(false);
            }}
            className={cn(
                'group/sidebar hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:outline-ring aria-[current=page]:bg-sidebar-accent aria-[current=page]:text-sidebar-accent-foreground flex min-h-11 w-full items-center gap-3 rounded-lg px-3 py-2 text-start focus-visible:outline-2 focus-visible:outline-offset-[-2px] aria-[current=page]:font-semibold',
                className,
            )}
        >
            <span
                className="grid size-5 shrink-0 place-items-center"
                aria-hidden="true"
            >
                {link.icon}
            </span>
            <SidebarLabel>{link.label}</SidebarLabel>
        </Link>
    );
}
export function SidebarTrigger({ className }: { className?: string }) {
    const { open, pinned, setOpen, isMobile, openMobile, dir } = useSidebar();
    const label =
        dir === 'rtl'
            ? isMobile
                ? 'فتح القائمة'
                : pinned
                  ? 'طي القائمة'
                  : 'تثبيت القائمة مفتوحة'
            : isMobile
              ? 'Open menu'
              : pinned
                ? 'Collapse sidebar'
                : 'Pin sidebar open';
    const button = (
        <button
            type="button"
            aria-label={label}
            aria-controls="dashboard-sidebar"
            aria-expanded={isMobile ? openMobile : open}
            aria-pressed={isMobile ? undefined : pinned}
            onClick={isMobile ? undefined : () => setOpen((value) => !value)}
            className={cn(
                'hover:bg-sidebar-accent focus-visible:outline-ring grid size-11 shrink-0 cursor-pointer place-items-center focus-visible:outline-2',
                className,
            )}
        >
            {isMobile ? (
                <Menu size={20} aria-hidden="true" />
            ) : pinned ? (
                <PanelLeftClose size={20} aria-hidden="true" />
            ) : (
                <PanelLeftOpen size={20} aria-hidden="true" />
            )}
        </button>
    );
    return isMobile ? <SheetTrigger asChild>{button}</SheetTrigger> : button;
}
export function SidebarInset({
    className,
    ...props
}: React.ComponentProps<'main'>) {
    return (
        <main
            className={cn('flex min-h-dvh min-w-0 flex-1 flex-col', className)}
            {...props}
        />
    );
}

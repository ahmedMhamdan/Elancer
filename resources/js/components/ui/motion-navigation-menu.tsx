// Adapted from the MotionNavigationMenu supplied by Ahmed.
// Local highlights replace the unavailable Unlumen registry dependency.
import * as React from 'react';
import { Link } from '@inertiajs/react';
import { cva } from 'class-variance-authority';
import {
    AnimatePresence,
    motion,
    useIsPresent,
    useReducedMotion,
} from 'framer-motion';
import { ChevronDown } from 'lucide-react';
import { cn } from '@/lib/utils';

type ContentRecord = { children: React.ReactNode; className?: string };
type MenuContext = {
    active: string;
    direction: number;
    id: string;
    open: (value: string, focus?: boolean) => void;
    close: () => void;
    register: (value: string, content: ContentRecord) => () => void;
};
const Context = React.createContext<MenuContext | null>(null);
const ItemContext = React.createContext('');
export function MotionNavigationMenu({
    children,
    className,
    mobile = false,
    ...props
}: React.ComponentProps<'nav'> & { mobile?: boolean }) {
    const root = React.useRef<HTMLElement>(null);
    const id = React.useId();
    const [active, setActive] = React.useState('');
    const [direction, setDirection] = React.useState(1);
    const [contents, setContents] = React.useState<
        Record<string, ContentRecord>
    >({});
    const [size, setSize] = React.useState({ width: 0, height: 0 });
    const [x, setX] = React.useState(0);
    const measure = React.useRef<HTMLDivElement>(null);
    const previous = React.useRef('');
    const focusPending = React.useRef(false);
    const timer = React.useRef<ReturnType<typeof setTimeout> | undefined>(
        undefined,
    );
    const reduced = useReducedMotion();
    const close = React.useCallback(() => {
        setActive('');
        previous.current = '';
    }, []);
    const open = React.useCallback((value: string, focus = false) => {
        focusPending.current = focus;
        if (focus && previous.current === value)
            root.current
                ?.querySelector<HTMLAnchorElement>('[data-menu-panel] a')
                ?.focus();
        clearTimeout(timer.current);
        const triggers = Array.from(
            root.current?.querySelectorAll<HTMLButtonElement>(
                '[data-menu-trigger]',
            ) ?? [],
        );
        const before = triggers.findIndex(
            (node) => node.dataset.menuTrigger === previous.current,
        );
        const after = triggers.findIndex(
            (node) => node.dataset.menuTrigger === value,
        );
        const rtl =
            root.current && getComputedStyle(root.current).direction === 'rtl';
        if (before >= 0 && after >= 0)
            setDirection((after > before ? 1 : -1) * (rtl ? -1 : 1));
        previous.current = value;
        setActive(value);
    }, []);
    const register = React.useCallback(
        (value: string, content: ContentRecord) => {
            setContents((current) => ({ ...current, [value]: content }));
            return () =>
                setContents((current) => {
                    const next = { ...current };
                    delete next[value];
                    return next;
                });
        },
        [],
    );
    React.useEffect(() => {
        const outside = (event: PointerEvent) => {
            if (
                event.target instanceof Node &&
                !root.current?.contains(event.target)
            )
                close();
        };
        document.addEventListener('pointerdown', outside);
        return () => {
            document.removeEventListener('pointerdown', outside);
            clearTimeout(timer.current);
        };
    }, [close]);
    React.useLayoutEffect(() => {
        const node = measure.current;
        if (!node || !active) return;
        const update = () => {
            const rect = node.getBoundingClientRect();
            setSize((old) =>
                old.width === rect.width && old.height === rect.height
                    ? old
                    : { width: rect.width, height: rect.height },
            );
            const parent = root.current?.getBoundingClientRect();
            const trigger = root.current
                ?.querySelector<HTMLElement>('[aria-expanded="true"]')
                ?.getBoundingClientRect();
            if (parent && trigger) {
                const center = trigger.left + trigger.width / 2;
                setX(
                    Math.max(
                        16 + rect.width / 2,
                        Math.min(
                            window.innerWidth - 16 - rect.width / 2,
                            center,
                        ),
                    ) - parent.left,
                );
            }
        };
        update();
        const observer = new ResizeObserver(update);
        observer.observe(node);
        window.addEventListener('resize', update);
        return () => {
            observer.disconnect();
            window.removeEventListener('resize', update);
        };
    }, [active, contents]);
    const context = React.useMemo(
        () => ({ active, direction, id, open, close, register }),
        [active, direction, id, open, close, register],
    );
    const spring = reduced
        ? { duration: 0 }
        : { type: 'spring' as const, stiffness: 350, damping: 32 };
    const content = contents[active];
    return (
        <Context.Provider value={context}>
            <nav
                {...props}
                ref={root}
                className={cn(
                    'motion-navigation',
                    mobile && 'motion-navigation-mobile',
                    className,
                )}
                onPointerEnter={() => clearTimeout(timer.current)}
                onPointerLeave={(event) => {
                    if (event.pointerType === 'mouse' && !mobile)
                        timer.current = setTimeout(close, 140);
                }}
                onBlur={(event) => {
                    if (!event.currentTarget.contains(event.relatedTarget))
                        close();
                }}
                onKeyDown={(event) => {
                    if (event.key === 'Escape' && active) {
                        event.preventDefault();
                        event.stopPropagation();
                        root.current
                            ?.querySelector<HTMLButtonElement>(
                                '[aria-expanded="true"]',
                            )
                            ?.focus();
                        close();
                    }
                }}
            >
                {children}
                <motion.div
                    className="motion-navigation-position"
                    initial={false}
                    animate={mobile ? {} : { left: x }}
                    transition={spring}
                >
                    <motion.div
                        className="motion-navigation-viewport"
                        initial={false}
                        animate={{
                            width: mobile ? '100%' : size.width,
                            height: content ? size.height : 0,
                            opacity: content ? 1 : 0,
                        }}
                        transition={spring}
                    >
                        <AnimatePresence
                            initial={false}
                            mode="wait"
                            custom={direction}
                        >
                            {content && (
                                <NavigationPanel
                                    key={active}
                                    ref={(node) => {
                                        if (node && focusPending.current) {
                                            focusPending.current = false;
                                            node.querySelector<HTMLAnchorElement>(
                                                'a',
                                            )?.focus();
                                        }
                                    }}
                                    id={id + '-' + active}
                                    data-menu-panel
                                    custom={direction}
                                    variants={{
                                        enter: (dir: number) => ({
                                            opacity: 0,
                                            x: reduced ? 0 : dir * 18,
                                        }),
                                        show: { opacity: 1, x: 0 },
                                        leave: (dir: number) => ({
                                            opacity: 0,
                                            x: reduced ? 0 : -dir * 12,
                                        }),
                                    }}
                                    initial="enter"
                                    animate="show"
                                    exit="leave"
                                    transition={
                                        reduced
                                            ? { duration: 0 }
                                            : { duration: 0.12 }
                                    }
                                    className={cn(
                                        'motion-navigation-content',
                                        content.className,
                                    )}
                                    onClick={(event) => {
                                        if (
                                            (
                                                event.target as HTMLElement
                                            ).closest('a')
                                        )
                                            close();
                                    }}
                                >
                                    {content.children}
                                </NavigationPanel>
                            )}
                        </AnimatePresence>
                    </motion.div>
                </motion.div>
                <div
                    className="motion-navigation-measure"
                    aria-hidden="true"
                    inert
                    ref={measure}
                >
                    {content && (
                        <div
                            className={cn(
                                'motion-navigation-content',
                                content.className,
                            )}
                        >
                            {content.children}
                        </div>
                    )}
                </div>
            </nav>
        </Context.Provider>
    );
}
function NavigationPanel(props: React.ComponentProps<typeof motion.div>) {
    const present = useIsPresent();
    return <motion.div {...props} inert={!present} />;
}
export function MotionNavigationMenuList(props: React.ComponentProps<'ul'>) {
    return (
        <ul
            {...props}
            className={cn('motion-navigation-list', props.className)}
        />
    );
}
export function MotionNavigationMenuItem({
    value = '',
    ...props
}: React.ComponentProps<'li'> & { value?: string }) {
    return (
        <ItemContext.Provider value={value}>
            <li {...props} />
        </ItemContext.Provider>
    );
}
export const motionNavigationMenuTriggerStyle = cva('elancer-explore-trigger');
export function MotionNavigationMenuTrigger({
    children,
    ...props
}: React.ComponentProps<'button'>) {
    const context = React.useContext(Context);
    const value = React.useContext(ItemContext);
    const reduced = useReducedMotion();
    const open = context?.active === value;
    const pointer = React.useRef('mouse');
    return (
        <button
            {...props}
            type="button"
            data-menu-trigger={value}
            className={cn(motionNavigationMenuTriggerStyle(), props.className)}
            aria-expanded={open}
            aria-controls={open ? context?.id + '-' + value : undefined}
            onPointerEnter={(event) => {
                if (event.pointerType === 'mouse') context?.open(value);
            }}
            onPointerDown={(event) => {
                pointer.current = event.pointerType;
            }}
            onClick={(event) =>
                open && (pointer.current !== 'mouse' || event.detail === 0)
                    ? context?.close()
                    : context?.open(value)
            }
            onKeyDown={(event) => {
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    context?.open(value, true);
                }
                if (
                    ['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(
                        event.key,
                    )
                ) {
                    const buttons = Array.from(
                        event.currentTarget
                            .closest('ul')
                            ?.querySelectorAll<HTMLButtonElement>('button') ??
                            [],
                    );
                    const rtl =
                        getComputedStyle(event.currentTarget).direction ===
                        'rtl';
                    const step =
                        (event.key === 'ArrowRight' ? 1 : -1) * (rtl ? -1 : 1);
                    const index =
                        event.key === 'Home'
                            ? 0
                            : event.key === 'End'
                              ? buttons.length - 1
                              : (buttons.indexOf(event.currentTarget) +
                                    step +
                                    buttons.length) %
                                buttons.length;
                    event.preventDefault();
                    buttons[index]?.focus();
                    context?.open(buttons[index]?.dataset.menuTrigger ?? '');
                }
            }}
        >
            {open && (
                <motion.span
                    aria-hidden="true"
                    className="motion-navigation-highlight"
                    layoutId={context?.id + '-highlight'}
                    transition={
                        reduced
                            ? { duration: 0 }
                            : { type: 'spring', stiffness: 350, damping: 32 }
                    }
                />
            )}
            <span>{children}</span>
            <motion.span
                aria-hidden="true"
                animate={{ rotate: open ? 180 : 0 }}
                transition={{ duration: reduced ? 0 : 0.2 }}
            >
                <ChevronDown size={14} />
            </motion.span>
        </button>
    );
}
export function MotionNavigationMenuContent({
    children,
    className,
}: ContentRecord) {
    const register = React.useContext(Context)?.register;
    const value = React.useContext(ItemContext);
    React.useLayoutEffect(
        () => register?.(value, { children, className }),
        [register, value, children, className],
    );
    return null;
}
export function MotionNavigationMenuLink(
    props: React.ComponentProps<typeof Link>,
) {
    return (
        <Link
            {...props}
            className={cn('motion-navigation-link', props.className)}
        />
    );
}
export default MotionNavigationMenu;

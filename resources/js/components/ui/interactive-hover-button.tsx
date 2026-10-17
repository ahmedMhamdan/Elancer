// Adapted from Magic UI's InteractiveHoverButton (https://magicui.design/docs/components/interactive-hover-button,
// registry file interactive-hover-button.tsx, MIT; see THIRD_PARTY_NOTICES.md). Kept: the dot that grows to fill the
// button (scale 100.8, so it reaches the far end of a wide label) and the label that slides out as the arrowed copy
// slides in. Changed for Elancer (plan 032): an Inertia link rather than a button; keyboard focus shows the same state
// as hover; the arrowed copy settles in the centre (the original shifts it 20px aside, which put it against the
// edge of a wide label); the slide mirrors in Arabic, where the arrow turns by the site's rule for
// lucide-arrow-right in elancer-locale.css; reduced motion swaps the states at once.
import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

export function InteractiveHoverButton({
    children,
    className,
    ...props
}: ComponentProps<typeof Link>) {
    const motion =
        'transition-all duration-300 motion-reduce:transition-none';
    return (
        <Link
            {...props}
            className={cn(
                'group relative inline-block w-auto cursor-pointer overflow-hidden rounded-full border bg-background p-2 px-6 text-center text-sm font-semibold whitespace-nowrap focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                className,
            )}
        >
            <span className="flex items-center justify-center gap-2">
                <span
                    aria-hidden="true"
                    className={cn(
                        'size-2 shrink-0 rounded-full bg-primary group-hover:scale-[100.8] group-focus-visible:scale-[100.8]',
                        motion,
                    )}
                />
                <span
                    className={cn(
                        'inline-block group-hover:translate-x-12 group-hover:opacity-0 group-focus-visible:translate-x-12 group-focus-visible:opacity-0 rtl:group-hover:-translate-x-12 rtl:group-focus-visible:-translate-x-12',
                        motion,
                    )}
                >
                    {children}
                </span>
            </span>
            <span
                aria-hidden="true"
                className={cn(
                    'absolute inset-0 z-10 flex items-center justify-center gap-2 text-primary-foreground opacity-0 translate-x-12 group-hover:translate-x-0 group-hover:opacity-100 group-focus-visible:translate-x-0 group-focus-visible:opacity-100 rtl:-translate-x-12 rtl:group-hover:translate-x-0 rtl:group-focus-visible:translate-x-0',
                    motion,
                )}
            >
                <span>{children}</span>
                <ArrowRight className="size-4" />
            </span>
        </Link>
    );
}

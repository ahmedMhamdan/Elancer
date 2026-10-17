// Adapted from Magic UI's BlurFade (https://magicui.design/docs/components/blur-fade, registry file blur-fade.tsx, MIT;
// see THIRD_PARTY_NOTICES.md). Kept: the short rise with opacity and blur, eased out, after an optional delay.
// Changed for Elancer (plan 032): no "use client", no exit animation, no in-view option; reduced motion renders the
// content as it is.
import type { ReactNode } from 'react';
import { motion, useReducedMotion } from 'motion/react';

export function BlurFade({
    children,
    className,
    delay = 0,
    duration = 0.4,
    offset = 6,
    blur = '6px',
}: {
    children: ReactNode;
    className?: string;
    delay?: number;
    duration?: number;
    offset?: number;
    blur?: string;
}) {
    const reduced = useReducedMotion();
    if (reduced) return <div className={className}>{children}</div>;
    return (
        <motion.div
            initial={{ y: offset, opacity: 0, filter: `blur(${blur})` }}
            animate={{ y: 0, opacity: 1, filter: 'blur(0px)' }}
            transition={{ delay: 0.04 + delay, duration, ease: 'easeOut' }}
            className={className}
        >
            {children}
        </motion.div>
    );
}

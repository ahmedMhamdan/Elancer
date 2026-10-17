// Adapted from Magic UI's NumberTicker (https://magicui.design/docs/components/number-ticker, registry file
// number-ticker.tsx, MIT; see THIRD_PARTY_NOTICES.md). Kept: the spring from the start value once the number is in
// view. Changed for Elancer (plan 032): no "use client"; the colour comes from the surrounding text; the number is
// formatted in the page's language; reduced motion shows the final value at once.
import {
    useEffect,
    useMemo,
    useRef,
    type ComponentPropsWithoutRef,
} from 'react';
import {
    useInView,
    useMotionValue,
    useReducedMotion,
    useSpring,
} from 'motion/react';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

interface NumberTickerProps extends ComponentPropsWithoutRef<'span'> {
    value: number;
    startValue?: number;
    delay?: number;
}

export function NumberTicker({
    value,
    startValue = 0,
    delay = 0,
    className,
    ...props
}: NumberTickerProps) {
    const { locale } = useTranslation();
    const reduced = useReducedMotion();
    const ref = useRef<HTMLSpanElement>(null);
    const motionValue = useMotionValue(startValue);
    const springValue = useSpring(motionValue, { damping: 60, stiffness: 100 });
    const isInView = useInView(ref, { once: true, margin: '0px' });
    const formatter = useMemo(
        () => new Intl.NumberFormat(locale, { maximumFractionDigits: 0 }),
        [locale],
    );

    useEffect(() => {
        if (reduced || !isInView) return;
        const timer = setTimeout(() => motionValue.set(value), delay * 1000);
        return () => clearTimeout(timer);
    }, [motionValue, isInView, delay, value, reduced]);

    useEffect(
        () =>
            springValue.on('change', (latest) => {
                if (ref.current && !reduced)
                    ref.current.textContent = formatter.format(
                        Math.round(latest),
                    );
            }),
        [springValue, reduced, formatter],
    );

    return (
        <span
            ref={ref}
            className={cn('inline-block tabular-nums', className)}
            {...props}
        >
            {formatter.format(reduced ? value : startValue)}
        </span>
    );
}

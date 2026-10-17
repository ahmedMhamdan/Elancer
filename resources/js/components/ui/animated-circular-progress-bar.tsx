// Adapted from Magic UI's AnimatedCircularProgressBar (https://magicui.design/docs/components/animated-circular-progress-bar,
// registry file animated-circular-progress-bar.tsx, MIT; see THIRD_PARTY_NOTICES.md). Kept: the gauge drawn as two
// arcs with a small gap, the primary arc's length and the secondary arc's remainder moving on the same transition.
// Changed for Elancer (plan 032): it fills from empty once after the first paint; the number carries its own label;
// reduced motion draws the final value at once.
import { useEffect, useState, type CSSProperties, type ReactNode } from 'react';
import { useReducedMotion } from 'motion/react';
import { cn } from '@/lib/utils';

export function AnimatedCircularProgressBar({
    max = 100,
    min = 0,
    value = 0,
    gaugePrimaryColor,
    gaugeSecondaryColor,
    className,
    children,
}: {
    max?: number;
    min?: number;
    value: number;
    gaugePrimaryColor: string;
    gaugeSecondaryColor: string;
    className?: string;
    children?: ReactNode;
}) {
    const reduced = useReducedMotion();
    const [shown, setShown] = useState(reduced ? value : min);
    useEffect(() => {
        if (reduced) return setShown(value);
        const frame = requestAnimationFrame(() => setShown(value));
        return () => cancelAnimationFrame(frame);
    }, [value, reduced]);
    const circumference = 2 * Math.PI * 45;
    const percentPx = circumference / 100;
    const currentPercent = Math.round(((shown - min) / (max - min)) * 100);

    return (
        <div
            className={cn('relative size-40 text-2xl font-semibold', className)}
            style={
                {
                    '--circle-size': '100px',
                    '--circumference': circumference,
                    '--percent-to-px': `${percentPx}px`,
                    '--gap-percent': '5',
                    '--offset-factor': '0',
                    '--transition-length': reduced ? '0s' : '1s',
                    '--delay': '0s',
                    '--percent-to-deg': '3.6deg',
                    transform: 'translateZ(0)',
                } as CSSProperties
            }
        >
            <svg
                fill="none"
                className="size-full"
                strokeWidth="2"
                viewBox="0 0 100 100"
                aria-hidden="true"
            >
                {currentPercent <= 90 && currentPercent >= 0 && (
                    <circle
                        cx="50"
                        cy="50"
                        r="45"
                        strokeWidth="10"
                        strokeDashoffset="0"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        style={
                            {
                                stroke: gaugeSecondaryColor,
                                '--stroke-percent': 90 - currentPercent,
                                '--offset-factor-secondary':
                                    'calc(1 - var(--offset-factor))',
                                strokeDasharray:
                                    'calc(var(--stroke-percent) * var(--percent-to-px)) var(--circumference)',
                                transform:
                                    'rotate(calc(1turn - 90deg - (var(--gap-percent) * var(--percent-to-deg) * var(--offset-factor-secondary)))) scaleY(-1)',
                                transition:
                                    'all var(--transition-length) ease var(--delay)',
                                transformOrigin:
                                    'calc(var(--circle-size) / 2) calc(var(--circle-size) / 2)',
                            } as CSSProperties
                        }
                    />
                )}
                <circle
                    cx="50"
                    cy="50"
                    r="45"
                    strokeWidth="10"
                    strokeDashoffset="0"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    style={
                        {
                            stroke: gaugePrimaryColor,
                            '--stroke-percent': currentPercent,
                            strokeDasharray:
                                'calc(var(--stroke-percent) * var(--percent-to-px)) var(--circumference)',
                            transition:
                                'var(--transition-length) ease var(--delay),stroke var(--transition-length) ease var(--delay)',
                            transitionProperty: 'stroke-dasharray,transform',
                            transform:
                                'rotate(calc(-90deg + var(--gap-percent) * var(--offset-factor) * var(--percent-to-deg)))',
                            transformOrigin:
                                'calc(var(--circle-size) / 2) calc(var(--circle-size) / 2)',
                        } as CSSProperties
                    }
                />
            </svg>
            <span className="absolute inset-0 m-auto size-fit">
                {children ?? currentPercent}
            </span>
        </div>
    );
}

import { motion, useReducedMotion } from 'motion/react';
import { useId, useRef, useState } from 'react';
import type { PointerEvent } from 'react';
import { cn } from '@/lib/utils';

type TextHoverEffectProps = {
    text: string;
    duration?: number;
    automatic?: boolean;
    className?: string;
};

export function TextHoverEffect({ text, duration = 0.18, automatic = false, className }: TextHoverEffectProps) {
    const svgRef = useRef<SVGSVGElement>(null);
    const id = useId().replace(/[^a-zA-Z0-9_-]/g, '');
    const gradientId = `footer-gradient-${id}`;
    const revealId = `footer-reveal-${id}`;
    const maskId = `footer-mask-${id}`;
    const reducedMotion = useReducedMotion();
    const [hovered, setHovered] = useState(false);
    const [maskPosition, setMaskPosition] = useState({ cx: '50%', cy: '50%' });

    const followPointer = (event: PointerEvent<SVGSVGElement>) => {
        const rect = svgRef.current?.getBoundingClientRect();
        if (!rect?.width || !rect.height || reducedMotion) return;
        setMaskPosition({
            cx: `${Math.max(0, Math.min(100, (event.clientX - rect.left) / rect.width * 100))}%`,
            cy: `${Math.max(0, Math.min(100, (event.clientY - rect.top) / rect.height * 100))}%`,
        });
    };

    return (
        <svg
            ref={svgRef}
            width="100%"
            height="100%"
            viewBox="0 0 600 160"
            xmlns="http://www.w3.org/2000/svg"
            onPointerEnter={() => setHovered(true)}
            onPointerLeave={() => setHovered(false)}
            onPointerMove={followPointer}
            className={cn('elancer-hover-word select-none', className)}
            aria-hidden="true"
            focusable="false"
        >
            <defs>
                <linearGradient id={gradientId} x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stopColor="#e0f9e7" />
                    <stop offset="30%" stopColor="#9ce5b3" />
                    <stop offset="60%" stopColor="#57d278" />
                    <stop offset="100%" stopColor="#27a66e" />
                </linearGradient>
                <motion.radialGradient
                    id={revealId}
                    r="28%"
                    initial={{ cx: '50%', cy: '50%' }}
                    animate={reducedMotion ? { cx: '50%', cy: '50%' } :
                        automatic && !hovered ? { cx: ['20%', '80%', '20%'], cy: '50%' } : maskPosition}
                    transition={reducedMotion ? { duration: 0 } :
                        automatic && !hovered ? { duration: 8, repeat: Infinity, ease: 'easeInOut' } :
                            { duration, ease: 'easeOut' }}
                >
                    <stop offset="0%" stopColor="white" />
                    <stop offset="100%" stopColor="black" />
                </motion.radialGradient>
                <mask id={maskId}>
                    <rect width="600" height="160" fill={`url(#${revealId})`} />
                </mask>
            </defs>
            <motion.text
                x="48%" y="54%" textAnchor="middle" dominantBaseline="middle"
                fill="transparent" stroke="#335e40" strokeWidth="0.6"
                className="elancer-hover-lettering"
                initial={reducedMotion ? false : { strokeDashoffset: 1000, strokeDasharray: 1000 }}
                animate={{ strokeDashoffset: 0, strokeDasharray: 1000 }}
                transition={{ duration: reducedMotion ? 0 : 3.5, ease: 'easeInOut' }}
            >{text}</motion.text>
            <text
                x="48%" y="54%" textAnchor="middle" dominantBaseline="middle"
                fill="transparent" stroke={`url(#${gradientId})`} strokeWidth="0.9"
                mask={`url(#${maskId})`}
                className="elancer-hover-lettering"
                opacity={hovered || automatic || reducedMotion ? 1 : 0.55}
            >{text}</text>
            <rect x="523" y="107" width="12" height="12" fill="#57d278" />
        </svg>
    );
}

export function FooterBackgroundGradient() {
    return <div className="elancer-footer-gradient" aria-hidden="true" />;
}

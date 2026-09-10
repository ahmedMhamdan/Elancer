import type { HTMLAttributes } from 'react';
import ThemeIcon from '@/components/theme-icon';
import { useAppearance } from '@/hooks/use-appearance';
import type { Appearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

const modes: { value: Appearance; label: string }[] = [
    { value: 'light', label: 'Light' },
    { value: 'dark', label: 'Dark' },
    { value: 'system', label: 'System' },
];

export default function AppearanceToggleTab({
    className,
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    const { appearance, updateAppearance } = useAppearance();
    return (
        <div
            role="group"
            aria-label="Color theme"
            className={cn(
                'border-border bg-muted inline-flex flex-wrap gap-1 rounded-xl border p-1',
                className,
            )}
            {...props}
        >
            {modes.map(({ value, label }) => (
                <button
                    type="button"
                    key={value}
                    aria-pressed={appearance === value}
                    onClick={() => updateAppearance(value)}
                    className={cn(
                        'focus-visible:outline-ring flex min-h-11 items-center gap-2 rounded-lg px-4 text-sm transition-colors focus-visible:outline-2 focus-visible:outline-offset-2',
                        appearance === value
                            ? 'bg-background text-foreground shadow-xs'
                            : 'text-muted-foreground hover:bg-background',
                    )}
                >
                    <ThemeIcon mode={value} />
                    {label}
                </button>
            ))}
        </div>
    );
}

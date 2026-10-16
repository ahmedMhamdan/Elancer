import { useTranslation } from '@/hooks/use-translation';
import ThemeIcon from '@/components/theme-icon';
import { AnimatedThemeToggler } from '@/components/ui/animated-theme-toggler';
import { useAppearance } from '@/hooks/use-appearance';

// The new theme grows as a circle from this button (Magic UI's AnimatedThemeToggler, plan 032).
export default function ThemeToggle({
    className = 'site-theme-toggle',
}: {
    className?: string;
}) {
    const { t } = useTranslation();
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const nextMode = resolvedAppearance === 'dark' ? 'light' : 'dark';
    const label = t(`Switch to ${nextMode} mode`);

    return (
        <AnimatedThemeToggler
            className={className}
            theme={resolvedAppearance}
            onThemeChange={updateAppearance}
            aria-label={label}
            title={label}
        >
            <ThemeIcon mode={nextMode} />
        </AnimatedThemeToggler>
    );
}

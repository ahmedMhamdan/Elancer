import { useTranslation } from '@/hooks/use-translation';
import ThemeIcon from '@/components/theme-icon';
import { useAppearance } from '@/hooks/use-appearance';

export default function ThemeToggle() {
    const { t } = useTranslation();
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const nextMode = resolvedAppearance === 'dark' ? 'light' : 'dark';
    const label = t(`Switch to ${nextMode} mode`);

    return (
        <button
            type="button"
            className="site-theme-toggle"
            aria-label={label}
            title={label}
            onClick={() => updateAppearance(nextMode)}
        >
            <ThemeIcon mode={nextMode} />
        </button>
    );
}

import { Moon, Sun } from 'lucide-react';
import { useAppearance } from '@/hooks/use-appearance';

export default function HomeThemeToggle() {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const isDark = resolvedAppearance === 'dark';
    const label = isDark ? 'Switch to light mode' : 'Switch to dark mode';

    return (
        <button
            type="button"
            className="elancer-theme-toggle"
            aria-label={label}
            title={label}
            data-dark={isDark}
            onClick={() => updateAppearance(isDark ? 'light' : 'dark')}
        >
            <Sun size={17} aria-hidden="true" className="elancer-theme-sun" />
            <Moon size={17} aria-hidden="true" className="elancer-theme-moon" />
        </button>
    );
}

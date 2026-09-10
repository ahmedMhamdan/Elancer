import { Monitor, Moon, Sun } from 'lucide-react';
import type { LucideProps } from 'lucide-react';
import type { Appearance } from '@/hooks/use-appearance';

const appearanceIcons = { light: Sun, dark: Moon, system: Monitor };

export default function ThemeIcon({
    mode,
    ...props
}: LucideProps & { mode: Appearance }) {
    const Icon = appearanceIcons[mode];
    return <Icon size={18} strokeWidth={1.75} aria-hidden="true" {...props} />;
}

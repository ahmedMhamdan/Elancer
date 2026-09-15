import { useTranslation } from '@/hooks/use-translation';
import Hero from '@/components/ui/animated-shader-hero';

// The live home page in pages/welcome.tsx is the Elancer-specific composition.
export default function HeroDemo() {
    const { t } = useTranslation();

    return (
        <Hero
            trustBadge={{
                text: t('A meeting place for talent & possibility'),
                icons: ['✦'],
            }}
            headline={{
                line1: t('Good people.'),
                line2: t('Extraordinary work.'),
            }}
            subtitle={t(
                'Bring your ambition. Find your people. Make something matter.',
            )}
            buttons={{
                primary: { text: t('Get started'), href: '/register' },
                secondary: { text: t('Log in'), href: '/login' },
            }}
        />
    );
}

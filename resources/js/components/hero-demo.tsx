import Hero from '@/components/ui/animated-shader-hero';

// The live home page in pages/welcome.tsx is the Elancer-specific composition.
export default function HeroDemo() {
    return (
        <Hero
            trustBadge={{
                text: 'A meeting place for talent & possibility',
                icons: ['✦'],
            }}
            headline={{ line1: 'Good people.', line2: 'Extraordinary work.' }}
            subtitle="Bring your ambition. Find your people. Make something matter."
            buttons={{
                primary: { text: 'Get started', href: '/register' },
                secondary: { text: 'Log in', href: '/login' },
            }}
        />
    );
}

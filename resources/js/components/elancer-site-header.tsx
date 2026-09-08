import { usePage } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import HomeThemeToggle from '@/components/home/home-theme-toggle';
import {
    MobileNav,
    MobileNavHeader,
    MobileNavMenu,
    MobileNavToggle,
    Navbar,
    NavbarButton,
    NavbarLogo,
    NavBody,
    NavItems,
} from '@/components/ui/resizable-navbar';
import { dashboard, home, login, register } from '@/routes';
import '../../css/elancer-site-header.css';

export default function ElancerSiteHeader({
    registration = false,
}: {
    registration?: boolean;
}) {
    const { auth } = usePage().props;
    const [isOpen, setIsOpen] = useState(false);
    const closeMenu = useCallback(() => setIsOpen(false), []);
    const destination = auth.user ? dashboard().url : register().url;
    const items = [
        {
            name: 'Explore skills',
            link: `${registration ? home().url : ''}#categories`,
        },
        {
            name: 'Talent showcase',
            link: `${registration ? home().url : ''}#featured-freelancers`,
        },
        {
            name: 'How it works',
            link: `${registration ? home().url : ''}#how-it-works`,
        },
    ];
    return (
        <Navbar>
            <NavBody>
                <NavbarLogo />
                <NavItems items={items} />
                <div className="flex shrink-0 items-center gap-2">
                    <HomeThemeToggle />
                    {!auth.user && (
                        <NavbarButton href={login().url} variant="secondary">
                            Log in
                        </NavbarButton>
                    )}
                    {!registration && (
                        <NavbarButton href={destination}>
                            {auth.user ? 'Workspace' : 'Join Elancer'}
                        </NavbarButton>
                    )}
                </div>
            </NavBody>
            <MobileNav>
                <MobileNavHeader>
                    <NavbarLogo />
                    <div className="flex items-center gap-2">
                        {!registration && (
                            <NavbarButton
                                href={destination}
                                className="px-4 text-xs"
                            >
                                {auth.user ? 'Workspace' : 'Join Elancer'}
                            </NavbarButton>
                        )}
                        <MobileNavToggle
                            isOpen={isOpen}
                            onClick={() => setIsOpen((current) => !current)}
                        />
                    </div>
                </MobileNavHeader>
                <MobileNavMenu isOpen={isOpen} onClose={closeMenu}>
                    <nav
                        aria-label="Mobile navigation"
                        className="flex flex-col"
                    >
                        {items.map((item) => (
                            <a
                                key={item.link}
                                href={item.link}
                                onClick={closeMenu}
                                className="elancer-resizable-link rounded-lg px-3 py-4 text-base hover:bg-[var(--el-soft)]"
                            >
                                {item.name}
                            </a>
                        ))}
                    </nav>
                    <div className="mt-2 flex items-center justify-between border-t border-[var(--el-border)] pt-4">
                        <HomeThemeToggle />
                        {!auth.user && (
                            <NavbarButton
                                href={login().url}
                                variant="secondary"
                                onClick={closeMenu}
                            >
                                Log in
                            </NavbarButton>
                        )}
                        {!registration && (
                            <NavbarButton
                                href={destination}
                                onClick={closeMenu}
                            >
                                {auth.user ? 'Workspace' : 'Create account'}
                            </NavbarButton>
                        )}
                    </div>
                </MobileNavMenu>
            </MobileNav>
        </Navbar>
    );
}

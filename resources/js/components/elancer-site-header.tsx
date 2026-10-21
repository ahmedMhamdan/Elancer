import { useTranslation } from '@/hooks/use-translation';
import ElancerSpotlight from '@/components/elancer-spotlight';
import LanguageToggle from '@/components/language-toggle';
import { Link, router, usePage } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import ExploreSkillsMenu from '@/components/explore-skills-menu';
import ThemeToggle from '@/components/theme-toggle';
import {
    MobileNav,
    MobileNavHeader,
    MobileNavMenu,
    MobileNavToggle,
    Navbar,
    NavbarButton,
    NavbarLogo,
    NavBody,
} from '@/components/ui/resizable-navbar';
import { dashboard, login, logout, register } from '@/routes';
import '../../css/elancer-site-header.css';

export default function ElancerSiteHeader({
    registration = false,
    loginPage = false,
}: {
    registration?: boolean;
    loginPage?: boolean;
}) {
    const { t } = useTranslation();

    const { auth } = usePage().props;
    const [isOpen, setIsOpen] = useState(false);
    const closeMenu = useCallback(() => setIsOpen(false), []);
    const destination = auth.user ? dashboard().url : register().url;
    // Plan 032 part 10: a demo visitor on the public pages can tell they are in the demo and leave it.
    const demo = auth.demo === true;
    const workspace = demo ? t('Demo workspace') : t('Workspace');
    const leave = demo ? t('Leave the demo') : t('Log out');
    const leaveHref = demo
        ? { url: '/demo/leave', method: 'post' as const }
        : logout();

    return (
        <Navbar>
            <NavBody>
                <NavbarLogo />
                <div className="elancer-desktop-links">
                    <ExploreSkillsMenu />
                </div>
                <div className="elancer-navbar-actions flex shrink-0 items-center gap-2">
                    <ElancerSpotlight />
                    <LanguageToggle />
                    <ThemeToggle />
                    {auth.user && (
                        <Link
                            href={leaveHref}
                            as="button"
                            onClick={() => router.flushAll()}
                            className="elancer-navbar-button elancer-navbar-button-secondary elancer-logout"
                        >
                            {leave}
                        </Link>
                    )}
                    {!auth.user && (
                        <NavbarButton
                            href={loginPage ? register().url : login().url}
                            variant="secondary"
                        >
                            {loginPage ? t('Join Elancer') : t('Log in')}
                        </NavbarButton>
                    )}
                    {!registration && (
                        <NavbarButton href={destination}>
                            {auth.user ? workspace : t('Join Elancer')}
                        </NavbarButton>
                    )}
                </div>
            </NavBody>
            <MobileNav>
                <MobileNavHeader>
                    <NavbarLogo />
                    <div className="flex items-center gap-2">
                        <ElancerSpotlight />
                        {!registration && (
                            <NavbarButton
                                href={destination}
                                className="px-4 text-xs"
                            >
                                {auth.user ? workspace : t('Join Elancer')}
                            </NavbarButton>
                        )}
                        <MobileNavToggle
                            isOpen={isOpen}
                            onClick={() => setIsOpen((current) => !current)}
                        />
                    </div>
                </MobileNavHeader>
                <MobileNavMenu isOpen={isOpen} onClose={closeMenu}>
                    <ExploreSkillsMenu mobile onNavigate={closeMenu} />
                    <div className="mt-2 flex items-center justify-between border-t border-[var(--el-border)] pt-4">
                        <LanguageToggle />
                        <ThemeToggle />
                        {auth.user && (
                            <Link
                                href={leaveHref}
                                as="button"
                                onClick={() => router.flushAll()}
                                className="elancer-navbar-button elancer-navbar-button-secondary elancer-logout"
                            >
                                {leave}
                            </Link>
                        )}
                        {!auth.user && (
                            <NavbarButton
                                href={loginPage ? register().url : login().url}
                                variant="secondary"
                                onClick={closeMenu}
                            >
                                {loginPage ? t('Join Elancer') : t('Log in')}
                            </NavbarButton>
                        )}
                        {!registration && (
                            <NavbarButton
                                href={destination}
                                onClick={closeMenu}
                            >
                                {auth.user ? workspace : t('Create account')}
                            </NavbarButton>
                        )}
                    </div>
                </MobileNavMenu>
            </MobileNav>
        </Navbar>
    );
}

import { usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    FolderKanban,
    LayoutDashboard,
    LayoutGrid,
    LogIn,
    Search,
    UserPlus,
    UserRound,
} from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';
import {
    MotionNavigationMenu,
    MotionNavigationMenuContent,
    MotionNavigationMenuItem,
    MotionNavigationMenuLink,
    MotionNavigationMenuList,
    MotionNavigationMenuTrigger,
} from '@/components/ui/motion-navigation-menu';

export default function ExploreSkillsMenu({
    mobile = false,
    onNavigate,
}: {
    mobile?: boolean;
    onNavigate?: () => void;
}) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const links = auth.user
        ? [
              {
                  href: '/dashboard',
                  title: t('Dashboard'),
                  description: t('Return to your workspace.'),
                  icon: LayoutDashboard,
              },
              {
                  href: '/my-profile',
                  title: t('My profile'),
                  description: t('Manage your profile and skills.'),
                  icon: UserRound,
              },
              ...(auth.user.workspace_role === 'client'
                  ? [
                        {
                            href: '/my-projects',
                            title: t('My projects'),
                            description: t('Manage your project drafts.'),
                            icon: FolderKanban,
                        },
                    ]
                  : []),
          ]
        : [
              {
                  href: '/register',
                  title: t('Create account'),
                  description: t('Join as a client or freelancer.'),
                  icon: UserPlus,
              },
              {
                  href: '/login',
                  title: t('Log in'),
                  description: t('Continue where you left off.'),
                  icon: LogIn,
              },
          ];
    return (
        <MotionNavigationMenu mobile={mobile} aria-label={t('Explore Elancer')}>
            <MotionNavigationMenuList>
                <MotionNavigationMenuItem value="discover">
                    <MotionNavigationMenuTrigger>
                        {t('Discover')}
                    </MotionNavigationMenuTrigger>
                    <MotionNavigationMenuContent>
                        <div className="elancer-discovery-menu">
                            <MotionNavigationMenuLink
                                href="/categories"
                                onClick={onNavigate}
                                className="elancer-menu-feature"
                            >
                                <LayoutGrid size={25} aria-hidden="true" />
                                <span>
                                    <strong>{t('Browse categories')}</strong>
                                    <span>
                                        {t(
                                            'Find the right field for your next project.',
                                        )}
                                    </span>
                                </span>
                                <ArrowUpRight size={20} aria-hidden="true" />
                            </MotionNavigationMenuLink>
                            <MotionNavigationMenuLink
                                href="/jobs"
                                onClick={onNavigate}
                            >
                                <Search size={22} aria-hidden="true" />
                                <span>
                                    <strong>{t('Search jobs')}</strong>
                                    <span>
                                        {t(
                                            'Explore projects by skill, budget, and category.',
                                        )}
                                    </span>
                                </span>
                                <ArrowUpRight size={18} aria-hidden="true" />
                            </MotionNavigationMenuLink>
                        </div>
                    </MotionNavigationMenuContent>
                </MotionNavigationMenuItem>
                <MotionNavigationMenuItem value="account">
                    <MotionNavigationMenuTrigger>
                        {auth.user ? t('Your workspace') : t('Get started')}
                    </MotionNavigationMenuTrigger>
                    <MotionNavigationMenuContent className="elancer-account-menu">
                        {links.map(
                            ({ href, title, description, icon: Icon }) => (
                                <MotionNavigationMenuLink
                                    key={href}
                                    href={href}
                                    onClick={onNavigate}
                                >
                                    <Icon size={21} aria-hidden="true" />
                                    <span>
                                        <strong>{title}</strong>
                                        <span>{description}</span>
                                    </span>
                                    <ArrowUpRight
                                        size={17}
                                        aria-hidden="true"
                                    />
                                </MotionNavigationMenuLink>
                            ),
                        )}
                    </MotionNavigationMenuContent>
                </MotionNavigationMenuItem>
            </MotionNavigationMenuList>
        </MotionNavigationMenu>
    );
}

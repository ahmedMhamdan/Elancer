import { usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    BookOpen,
    Compass,
    FolderKanban,
    LayoutDashboard,
    Lightbulb,
    ListChecks,
    UserRound,
} from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';
import { publicGuides } from '@/data/public-guides';
import {
    MotionNavigationMenu,
    MotionNavigationMenuContent,
    MotionNavigationMenuItem,
    MotionNavigationMenuLink,
    MotionNavigationMenuList,
    MotionNavigationMenuTrigger,
} from '@/components/ui/motion-navigation-menu';

const guideIcons = [Compass, ListChecks, Lightbulb, UserRound, BookOpen];

export default function ExploreSkillsMenu({
    mobile = false,
    onNavigate,
}: {
    mobile?: boolean;
    onNavigate?: () => void;
}) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const guides = publicGuides(t);
    const links = auth.user
        ? [
              {
                  href: '/my-proposals',
                  title: t('My proposals'),
                  description: t('Track your applications and drafts.'),
                  icon: FolderKanban,
              },
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
        : [];
    return (
        <MotionNavigationMenu mobile={mobile} aria-label={t('Explore Elancer')}>
            <MotionNavigationMenuList>
                {guides.map((guide, index) => {
                    const Icon = guideIcons[index];
                    return (
                        <MotionNavigationMenuItem
                            key={guide.id}
                            value={guide.id}
                        >
                            <MotionNavigationMenuTrigger>
                                {guide.title}
                            </MotionNavigationMenuTrigger>
                            <MotionNavigationMenuContent className="elancer-guide-menu">
                                <div className="elancer-guide-menu-intro">
                                    <Icon size={22} aria-hidden="true" />
                                    <p>{guide.intro}</p>
                                </div>
                                {guide.sections.map((section) => (
                                    <MotionNavigationMenuLink
                                        key={section.id}
                                        href={
                                            '/learn/' +
                                            guide.id +
                                            '#' +
                                            section.id
                                        }
                                        onClick={onNavigate}
                                    >
                                        <span>
                                            <strong>{section.title}</strong>
                                            <span>{section.description}</span>
                                        </span>
                                        <ArrowUpRight
                                            size={18}
                                            aria-hidden="true"
                                        />
                                    </MotionNavigationMenuLink>
                                ))}
                            </MotionNavigationMenuContent>
                        </MotionNavigationMenuItem>
                    );
                })}
                {auth.user && (
                    <MotionNavigationMenuItem value="account">
                        <MotionNavigationMenuTrigger>
                            {t('Your workspace')}
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
                )}
            </MotionNavigationMenuList>
        </MotionNavigationMenu>
    );
}

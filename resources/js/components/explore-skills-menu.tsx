import { usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    ArrowUpRight,
    BookOpen,
    BriefcaseBusiness,
    Compass,
    Lightbulb,
    LifeBuoy,
    ListChecks,
    UserRound,
    UsersRound,
} from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';
import { publicGuides } from '@/data/public-guides';
import {
    MotionNavigationMenu,
    MotionNavigationMenuContent,
    MotionNavigationMenuItem,
    MotionNavigationMenuLink,
    MotionNavigationMenuList,
    MotionNavigationMenuTopLink,
    MotionNavigationMenuTrigger,
} from '@/components/ui/motion-navigation-menu';

const guideIcons = [Compass, ListChecks, Lightbulb, UserRound, BookOpen];

type Entry = {
    href: string;
    title: string;
    description: string;
    icon: LucideIcon;
};

function EntryLinks({
    entries,
    onNavigate,
}: {
    entries: Entry[];
    onNavigate?: () => void;
}) {
    return entries.map(({ href, title, description, icon: Icon }) => (
        <MotionNavigationMenuLink key={href} href={href} onClick={onNavigate}>
            <Icon size={21} aria-hidden="true" />
            <span>
                <strong>{title}</strong>
                <span>{description}</span>
            </span>
            <ArrowUpRight size={17} aria-hidden="true" />
        </MotionNavigationMenuLink>
    ));
}

// Plan 032 part 5: Explore (work and freelancers), Messages for members, Categories and Learn (the guides and Help).
export default function ExploreSkillsMenu({
    mobile = false,
    onNavigate,
}: {
    mobile?: boolean;
    onNavigate?: () => void;
}) {
    const { t } = useTranslation();
    const { auth, inbox } = usePage().props;
    const work: Entry = {
        href: '/jobs',
        title: t('Find work'),
        description: t('For freelancers: find projects that fit your skills.'),
        icon: BriefcaseBusiness,
    };
    const people: Entry = {
        href: '/freelancers',
        title: t('Find freelancers'),
        description: t(
            'For business owners: find the right person for your project.',
        ),
        icon: UsersRound,
    };
    const explore =
        auth.user?.workspace_role === 'client'
            ? [people, work]
            : [work, people];
    const learn: Entry[] = [
        ...publicGuides(t).map((guide, index) => ({
            href: '/learn/' + guide.id,
            title: guide.title,
            description: guide.intro,
            icon: guideIcons[index],
        })),
        {
            href: '/help',
            title: t('Help centre'),
            description: t(
                'Start with the common questions and account controls.',
            ),
            icon: LifeBuoy,
        },
    ];
    return (
        <MotionNavigationMenu mobile={mobile} aria-label={t('Explore Elancer')}>
            <MotionNavigationMenuList>
                <MotionNavigationMenuItem value="explore">
                    <MotionNavigationMenuTrigger>
                        {t('Explore')}
                    </MotionNavigationMenuTrigger>
                    <MotionNavigationMenuContent className="elancer-link-menu">
                        <EntryLinks entries={explore} onNavigate={onNavigate} />
                    </MotionNavigationMenuContent>
                </MotionNavigationMenuItem>
                {auth.user && (
                    <MotionNavigationMenuItem>
                        <MotionNavigationMenuTopLink
                            href="/messages"
                            onClick={onNavigate}
                            aria-label={
                                inbox.unread > 0
                                    ? t('Messages, :count unread', {
                                          count: inbox.unread,
                                      })
                                    : undefined
                            }
                        >
                            {t('Messages')}
                            {inbox.unread > 0 && (
                                <span
                                    className="elancer-nav-count"
                                    aria-hidden="true"
                                >
                                    {inbox.unread > 99 ? '99+' : inbox.unread}
                                </span>
                            )}
                        </MotionNavigationMenuTopLink>
                    </MotionNavigationMenuItem>
                )}
                <MotionNavigationMenuItem>
                    <MotionNavigationMenuTopLink
                        href="/categories"
                        onClick={onNavigate}
                    >
                        {t('Categories')}
                    </MotionNavigationMenuTopLink>
                </MotionNavigationMenuItem>
                <MotionNavigationMenuItem value="learn">
                    <MotionNavigationMenuTrigger>
                        {t('Learn')}
                    </MotionNavigationMenuTrigger>
                    <MotionNavigationMenuContent className="elancer-link-menu">
                        <EntryLinks entries={learn} onNavigate={onNavigate} />
                    </MotionNavigationMenuContent>
                </MotionNavigationMenuItem>
            </MotionNavigationMenuList>
        </MotionNavigationMenu>
    );
}

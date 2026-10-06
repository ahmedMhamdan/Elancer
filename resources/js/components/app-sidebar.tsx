import { useTranslation } from '@/hooks/use-translation';
// Preserve Ahmed's supplied sidebar primitive. Grouped item disclosure adapts
// TailAdmin src/layout/AppSidebar.tsx (MIT); see THIRD_PARTY_NOTICES.md.
import { Link, router, usePage } from '@inertiajs/react';
import { useId, useState, type ReactNode } from 'react';
import { motion, useReducedMotion } from 'framer-motion';
import {
    Banknote,
    BriefcaseBusiness,
    ChevronDown,
    Compass,
    CreditCard,
    FileText,
    Flag,
    FolderKanban,
    GalleryVerticalEnd,
    Handshake,
    LayoutDashboard,
    LayoutGrid,
    LogOut,
    Mail,
    MessageSquare,
    MessagesSquare,
    ReceiptText,
    Search,
    Settings,
    ShieldCheck,
    UserRound,
    UserRoundX,
    type LucideIcon,
} from 'lucide-react';
import ElancerWordmark from '@/components/elancer-wordmark';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    SidebarBody,
    SidebarLabel,
    SidebarLink,
    useSidebar,
} from '@/components/ui/sidebar';
import { useInitials } from '@/hooks/use-initials';
import { dashboard, logout } from '@/routes';
import { edit } from '@/routes/profile';

export function AppSidebar() {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const path = usePage().url.split('?')[0];
    const { open, isMobile, setOpen, setOpenMobile } = useSidebar();
    const wide = open || isMobile;
    const initials = useInitials();
    const reduced = useReducedMotion();
    const spring = reduced
        ? { duration: 0 }
        : { type: 'spring' as const, stiffness: 350, damping: 32 };
    const id = useId();
    const [expanded, setExpanded] = useState<Record<string, boolean>>({});
    const active = (href: string, exact = false) =>
        path === href || (!exact && path.startsWith(href + '/'));
    const groups: {
        id: string;
        label: string;
        icon: LucideIcon;
        links: {
            label: string;
            href: string;
            icon: ReactNode;
            exact?: boolean;
        }[];
    }[] = [
        {
            id: 'work',
            label: t('My work'),
            icon: BriefcaseBusiness,
            links: [
                {
                    label: t('My projects'),
                    href: '/my-projects',
                    icon: <FolderKanban size={20} />,
                },
                {
                    label: t('My proposals'),
                    href: '/my-proposals',
                    icon: <FileText size={20} />,
                },
                {
                    label: t('Offers'),
                    href: '/offers',
                    icon: <Mail size={20} />,
                },
                {
                    label: t('Contracts'),
                    href: '/contracts',
                    icon: <Handshake size={20} />,
                },
            ],
        },
        {
            id: 'finance',
            label: t('Payments and finance'),
            icon: Banknote,
            links: [
                {
                    label: t('Finance overview'),
                    href: '/finance',
                    exact: true,
                    icon: <CreditCard size={20} />,
                },
                {
                    label: t('Payment history'),
                    href: '/finance/payments',
                    icon: <ReceiptText size={20} />,
                },
            ],
        },
        {
            id: 'communication',
            label: t('Communication'),
            icon: MessagesSquare,
            links: [
                {
                    label: t('Messages'),
                    href: '/messages',
                    icon: <MessageSquare size={20} />,
                },
                {
                    label: t('Invitations'),
                    href: '/invitations',
                    icon: <Mail size={20} />,
                },
                {
                    label: t('Blocked accounts'),
                    href: '/blocked-accounts',
                    icon: <UserRoundX size={20} />,
                },
                {
                    label: t('My reports'),
                    href: '/my-reports',
                    icon: <Flag size={20} />,
                },
            ],
        },
        {
            id: 'marketplace',
            label: t('Marketplace'),
            icon: Compass,
            links: [
                {
                    label: t('Find jobs'),
                    href: '/jobs',
                    icon: <Search size={20} />,
                },
                {
                    label: t('Find freelancers'),
                    href: '/freelancers',
                    icon: <UserRound size={20} />,
                },
                {
                    label: t('Categories'),
                    href: '/categories',
                    icon: <LayoutGrid size={20} />,
                },
            ],
        },
        {
            id: 'account',
            label: t('Account'),
            icon: Settings,
            links: [
                {
                    label: t('My profile'),
                    href: '/my-profile',
                    icon: <UserRound size={20} />,
                },
                {
                    label: t('Portfolio'),
                    href: '/my-portfolio',
                    icon: <GalleryVerticalEnd size={20} />,
                },
                {
                    label: t('Account settings'),
                    href: edit().url,
                    icon: <Settings size={20} />,
                },
            ],
        },
        ...(auth.user.is_admin === true || auth.user.is_super_admin === true
            ? [
                  {
                      id: 'administration',
                      label: t('Administration'),
                      icon: ShieldCheck,
                      links: [
                          {
                              label: t('Categories'),
                              href: '/admin/categories',
                              icon: <LayoutGrid size={20} />,
                          },
                          {
                              label: t('Reports'),
                              href: '/admin/reports',
                              icon: <Flag size={20} />,
                          },
                          ...(auth.user.is_super_admin === true
                              ? [
                                    {
                                        label: t('Admin access'),
                                        href: '/admin/administrators',
                                        icon: <ShieldCheck size={20} />,
                                    },
                                    {
                                        label: t('Identity reviews'),
                                        href: '/admin/identity',
                                        icon: <ShieldCheck size={20} />,
                                    },
                                ]
                              : []),
                      ],
                  },
              ]
            : []),
    ];
    return (
        <SidebarBody className="gap-4">
            <Link
                href={dashboard()}
                aria-label={t('Elancer dashboard')}
                onClick={() => setOpenMobile(false)}
                className="sidebar-brand flex h-11 shrink-0 items-center overflow-hidden rounded-lg px-3"
            >
                {wide ? (
                    <ElancerWordmark />
                ) : (
                    <span
                        className="text-2xl font-semibold"
                        dir="ltr"
                        aria-hidden="true"
                    >
                        E<span className="text-primary">.</span>
                    </span>
                )}
            </Link>
            <nav
                aria-label={t('Main navigation')}
                className="sidebar-navigation min-h-0 flex-1 overflow-x-hidden overflow-y-auto"
                data-expanded={wide}
            >
                <SidebarLink
                    link={{
                        label: t('Overview'),
                        href: dashboard().url,
                        icon: <LayoutDashboard size={20} />,
                    }}
                    aria-current={active('/dashboard') ? 'page' : undefined}
                />
                {groups.map((group) => {
                    const Icon = group.icon;
                    const current = group.links.some((link) =>
                        active(link.href, link.exact),
                    );
                    const groupOpen = expanded[group.id] ?? current;
                    return (
                        <div
                            key={group.id}
                            className="sidebar-navigation-group"
                        >
                            <button
                                type="button"
                                className="sidebar-group-trigger"
                                aria-label={group.label}
                                title={!wide ? group.label : undefined}
                                aria-expanded={wide && groupOpen}
                                aria-controls={id + '-' + group.id}
                                data-active={current}
                                onClick={() => {
                                    if (!wide && !isMobile) setOpen(true);
                                    setExpanded((previous) => ({
                                        ...previous,
                                        [group.id]: !wide || !groupOpen,
                                    }));
                                }}
                            >
                                <Icon
                                    size={20}
                                    className="shrink-0"
                                    aria-hidden="true"
                                />
                                <SidebarLabel>{group.label}</SidebarLabel>
                                {wide && (
                                    <ChevronDown
                                        size={16}
                                        className={
                                            groupOpen
                                                ? 'ms-auto shrink-0 rotate-180 transition-transform duration-200 motion-reduce:transition-none'
                                                : 'ms-auto shrink-0 transition-transform duration-200 motion-reduce:transition-none'
                                        }
                                        aria-hidden="true"
                                    />
                                )}
                            </button>
                            <motion.div
                                id={id + '-' + group.id}
                                inert={!wide || !groupOpen}
                                aria-hidden={!wide || !groupOpen}
                                initial={false}
                                animate={{
                                    height: wide && groupOpen ? 'auto' : 0,
                                    opacity: wide && groupOpen ? 1 : 0,
                                }}
                                transition={spring}
                                className="overflow-hidden"
                            >
                                <div className="sidebar-group-links">
                                    {group.links.map(({ exact, ...link }) => (
                                        <SidebarLink
                                            key={link.href}
                                            link={link}
                                            aria-current={
                                                active(link.href, exact)
                                                    ? 'page'
                                                    : undefined
                                            }
                                        />
                                    ))}
                                </div>
                            </motion.div>
                        </div>
                    );
                })}
            </nav>
            <div className="border-sidebar-border flex shrink-0 flex-col gap-2 border-t pt-3">
                <SidebarLink
                    link={{
                        label: t('Log out'),
                        href: logout(),
                        icon: <LogOut size={20} />,
                    }}
                    as="button"
                    onClick={() => router.flushAll()}
                />
                <Link
                    href={edit()}
                    aria-label={auth.user.name + ' — ' + t('Account settings')}
                    onClick={() => setOpenMobile(false)}
                    className="sidebar-account hover:bg-sidebar-accent flex min-h-11 items-center gap-3 overflow-hidden rounded-lg px-2"
                >
                    <Avatar className="size-7 shrink-0">
                        <AvatarImage
                            src={auth.user.avatar ?? undefined}
                            alt=""
                        />
                        <AvatarFallback className="bg-sidebar-accent text-sidebar-accent-foreground text-xs">
                            {initials(auth.user.name)}
                        </AvatarFallback>
                    </Avatar>
                    <SidebarLabel>{auth.user.name}</SidebarLabel>
                </Link>
            </div>
        </SidebarBody>
    );
}

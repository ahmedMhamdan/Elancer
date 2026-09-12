// Dashboard integration of Ahmed's supplied SidebarDemo; real Elancer routes,
// role-aware links, wordmark and account replace the demo's placeholders.
import { Link, router, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    Compass,
    FolderTree,
    LayoutDashboard,
    LogOut,
    Settings,
    ShieldCheck,
    UserRound,
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
import { dashboard, home, logout } from '@/routes';
import { edit } from '@/routes/profile';

export function AppSidebar() {
    const { auth } = usePage().props;
    const { url } = usePage();
    const { open, isMobile, setOpenMobile, reducedMotion } = useSidebar();
    const initials = useInitials();
    const ar = auth.user.locale === 'ar';
    const text = ar
        ? {
              overview: 'نظرة عامة',
              settings: 'إعدادات الحساب',
              profile: 'ملفي الشخصي',
              explore: 'استكشاف المهارات',
              categories: 'التصنيفات',
              admins: 'صلاحيات الإدارة',
              logout: 'تسجيل الخروج',
              navigation: 'التنقل الرئيسي',
              dashboard: 'لوحة تحكم Elancer',
          }
        : {
              overview: 'Overview',
              settings: 'Account settings',
              profile: 'My profile',
              explore: 'Explore skills',
              categories: 'Categories',
              admins: 'Admin access',
              logout: 'Log out',
              navigation: 'Main navigation',
              dashboard: 'Elancer dashboard',
          };
    const links = [
        {
            label: text.overview,
            href: dashboard().url,
            icon: <LayoutDashboard size={20} />,
        },
        {
            label: text.profile,
            href: dashboard().url + '#profile-form',
            icon: <UserRound size={20} />,
        },
        {
            label: text.settings,
            href: edit().url,
            icon: <Settings size={20} />,
        },
        ...(auth.user.is_admin === true || auth.user.is_super_admin === true
            ? [
                  {
                      label: text.categories,
                      href: '/admin/categories',
                      icon: <FolderTree size={20} />,
                  },
              ]
            : []),
        ...(auth.user.is_super_admin === true
            ? [
                  {
                      label: text.admins,
                      href: '/admin/administrators',
                      icon: <ShieldCheck size={20} />,
                  },
              ]
            : []),
        {
            label: text.explore,
            href: home().url + '#categories',
            icon: <Compass size={20} />,
        },
    ];
    const path = url.split('?')[0];
    return (
        <SidebarBody className="justify-between gap-6">
            <div className="flex min-h-0 flex-1 flex-col overflow-x-hidden overflow-y-auto">
                <Link
                    href={dashboard()}
                    aria-label={text.dashboard}
                    onClick={() => setOpenMobile(false)}
                    className="focus-visible:outline-ring flex h-11 shrink-0 items-center overflow-hidden rounded-lg px-3 focus-visible:outline-2 focus-visible:outline-offset-[-2px]"
                >
                    <span
                        className="relative block h-9 w-40 shrink-0"
                        dir="ltr"
                        aria-hidden="true"
                    >
                        <motion.span
                            className="absolute inset-0 flex items-center"
                            initial={false}
                            animate={{
                                opacity: open || isMobile ? 1 : 0,
                                x: open || isMobile ? 0 : -8,
                            }}
                            transition={{ duration: reducedMotion ? 0 : 0.2 }}
                        >
                            <ElancerWordmark />
                        </motion.span>
                        <motion.span
                            className="text-foreground absolute inset-y-0 start-0 flex items-center text-2xl font-semibold"
                            initial={false}
                            animate={{
                                opacity: open || isMobile ? 0 : 1,
                                scale: open || isMobile ? 0.85 : 1,
                            }}
                            transition={{ duration: reducedMotion ? 0 : 0.16 }}
                        >
                            E<span className="text-primary">.</span>
                        </motion.span>
                    </span>{' '}
                </Link>
                <nav
                    aria-label={text.navigation}
                    className="mt-8 flex flex-col gap-2"
                >
                    {links.map((link) => (
                        <SidebarLink
                            key={link.href}
                            link={link}
                            aria-current={
                                !link.href.includes('#') &&
                                (path === link.href ||
                                    path.startsWith(link.href + '/'))
                                    ? 'page'
                                    : undefined
                            }
                        />
                    ))}
                </nav>
            </div>
            <div className="border-sidebar-border flex shrink-0 flex-col gap-3 border-t pt-4">
                <SidebarLink
                    link={{
                        label: text.logout,
                        href: logout(),
                        icon: <LogOut size={20} />,
                    }}
                    as="button"
                    onClick={() => router.flushAll()}
                />
                <Link
                    href={edit()}
                    aria-label={auth.user.name + ' — ' + text.settings}
                    onClick={() => setOpenMobile(false)}
                    className="hover:bg-sidebar-accent focus-visible:outline-ring flex min-h-11 items-center gap-3 overflow-hidden rounded-lg px-2 focus-visible:outline-2 focus-visible:outline-offset-[-2px]"
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

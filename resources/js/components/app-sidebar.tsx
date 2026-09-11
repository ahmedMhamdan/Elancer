import { Link, usePage } from '@inertiajs/react';
import { Compass, LayoutGrid, Settings, UserRound } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { dashboard, home } from '@/routes';
import { edit } from '@/routes/profile';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    { title: 'Overview', href: dashboard(), icon: LayoutGrid },
    { title: 'Account settings', href: edit(), icon: Settings },
];

export function AppSidebar() {
    const { auth } = usePage().props;
    const items =
        auth.user.is_admin === true
            ? [
                  ...mainNavItems,
                  {
                      title:
                          auth.user.locale === 'ar'
                              ? 'التصنيفات'
                              : 'Categories',
                      href: '/admin/categories',
                      icon: LayoutGrid,
                  },
              ]
            : mainNavItems;
    const { isMobile, setOpenMobile } = useSidebar();
    const closeOnMobile = () => {
        if (isMobile) setOpenMobile(false);
    };

    return (
        <Sidebar
            collapsible="offcanvas"
            variant="sidebar"
            className="workspace-sidebar"
        >
            <SidebarHeader className="px-4 py-6 group-data-[collapsible=icon]:px-2">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="hover:bg-transparent"
                        >
                            <Link
                                href={dashboard()}
                                aria-label="Elancer dashboard"
                                onClick={closeOnMobile}
                            >
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>
            <SidebarContent>
                <NavMain items={items} />
                <SidebarMenu className="px-4 group-data-[collapsible=icon]:px-2">
                    <SidebarMenuItem>
                        <SidebarMenuButton asChild tooltip="My profile">
                            <Link
                                href={dashboard().url + '#profile-form'}
                                onClick={closeOnMobile}
                            >
                                <UserRound aria-hidden="true" />
                                <span>My profile</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <SidebarMenuItem>
                        <SidebarMenuButton asChild tooltip="Explore skills">
                            <Link
                                href={home().url + '#categories'}
                                onClick={closeOnMobile}
                            >
                                <Compass aria-hidden="true" />
                                <span>Explore skills</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarContent>
            <SidebarFooter className="border-sidebar-border mt-5 border-t p-3">
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

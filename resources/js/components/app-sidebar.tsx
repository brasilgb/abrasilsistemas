import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    LayoutGrid,
    Newspaper,
    Settings,
    Target,
    Users,
} from 'lucide-react';
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
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as leadsIndex } from '@/routes/leads';
import { edit as profileEdit } from '@/routes/profile';
import { index as usersIndex } from '@/routes/users';
import type { NavItem, User } from '@/types';

const adminNavItems: NavItem[] = [
    {
        title: 'Blog',
        href: '/admin/blog/posts',
        icon: Newspaper,
    },
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Prospecção',
        href: leadsIndex(),
        icon: Target,
    },
    {
        title: 'Usuários',
        href: usersIndex(),
        icon: Users,
    },
    {
        title: 'Minha biblioteca',
        href: '/minha-biblioteca',
        icon: BookOpen,
    },
    {
        title: 'Configurações',
        href: profileEdit(),
        icon: Settings,
    },
];

export function AppSidebar() {
    const { auth } = usePage<{ auth: { user: User } }>().props;
    const mainNavItems =
        auth.user.role === 'admin'
            ? adminNavItems
            : adminNavItems.filter((item) =>
                  ['Minha biblioteca', 'Configurações'].includes(item.title),
              );

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

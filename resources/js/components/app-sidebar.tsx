import { Link } from '@inertiajs/react';
import { BookOpen, Building2, ClipboardCheck, ClipboardList, FileSignature, FolderGit2, GraduationCap, LayoutGrid, Users } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
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
import {
    Agreements as AgreementsPermission,
    Institutions as InstitutionsPermission,
    Mobilities as MobilitiesPermission,
    Requirements as RequirementsPermission,
    Users as UsersPermission,
} from '@/generated/permissions';
import { usePermissions } from '@/hooks/use-permissions';
import { dashboard } from '@/routes';
import { index as requestsIndex } from '@/routes/requests';
import { index as usersIndex } from '@/routes/users';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Requests',
        href: requestsIndex(),
        icon: ClipboardList,
    },
];

const usersNavItem: NavItem = {
    title: 'Users',
    href: usersIndex(),
    icon: Users,
};

const mobilitiesNavItem: NavItem = {
    title: 'Academic mobility',
    href: '/academic-mobility/mobilities',
    icon: GraduationCap,
};

const institutionsNavItem: NavItem = {
    title: 'Institutions',
    href: '/academic-mobility/institutions',
    icon: Building2,
};

const agreementsNavItem: NavItem = {
    title: 'Agreements',
    href: '/academic-mobility/agreements',
    icon: FileSignature,
};

const requirementsNavItem: NavItem = {
    title: 'Requirements',
    href: '/academic-mobility/requirements',
    icon: ClipboardCheck,
};

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { can } = usePermissions();
    const navItems = [
        ...mainNavItems,
        ...(can(MobilitiesPermission.index) ? [mobilitiesNavItem] : []),
        ...(can(InstitutionsPermission.index) ? [institutionsNavItem] : []),
        ...(can(AgreementsPermission.index) ? [agreementsNavItem] : []),
        ...(can(RequirementsPermission.index) ? [requirementsNavItem] : []),
        ...(can(UsersPermission.index) ? [usersNavItem] : []),
    ];

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
                <NavMain items={navItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

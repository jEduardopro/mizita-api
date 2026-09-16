import { Link, usePage } from '@inertiajs/react';
import { Calendar, ListChecks, Settings, Users, type LucideIcon } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useAuthorization } from '@/hooks/use-authorization';
import type { PermissionName } from '@/lib/authorization';

type NavItem = {
    href: string;
    labelKey: 'nav.calendar' | 'nav.services' | 'nav.customers' | 'nav.settings';
    icon: LucideIcon;
    permission?: PermissionName;
};

const navItems: NavItem[] = [
    { href: '/calendar', labelKey: 'nav.calendar', icon: Calendar },
    { href: '/services', labelKey: 'nav.services', icon: ListChecks, permission: 'view_services' },
    { href: '/customers', labelKey: 'nav.customers', icon: Users },
    {
        href: '/settings/business',
        labelKey: 'nav.settings',
        icon: Settings,
        permission: 'view_business_settings',
    },
];

function isActive(url: string, href: string): boolean {
    return url === href || url.startsWith(`${href}/`) || url.startsWith(`${href}?`);
}

export function AdminNav() {
    const { t } = useTranslation('admin');
    const { url } = usePage();
    const { can } = useAuthorization();

    const visibleItems = navItems.filter(
        (item) => item.permission === undefined || can(item.permission),
    );

    return (
        <SidebarGroup>
            <SidebarGroupContent>
                <SidebarMenu>
                    {visibleItems.map((item) => {
                        const label = t(item.labelKey);
                        const Icon = item.icon;

                        return (
                            <SidebarMenuItem key={item.href}>
                                <SidebarMenuButton
                                    asChild
                                    isActive={isActive(url, item.href)}
                                    tooltip={label}
                                    className="h-11 md:h-8"
                                >
                                    <Link href={item.href}>
                                        <Icon />
                                        <span>{label}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        );
                    })}
                </SidebarMenu>
            </SidebarGroupContent>
        </SidebarGroup>
    );
}

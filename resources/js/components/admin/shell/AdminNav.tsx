import { Link, usePage } from '@inertiajs/react';
import { Calendar, ListChecks, Settings, Users, type LucideIcon } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useAuthorization } from '@/hooks/use-authorization';
import type { PermissionName } from '@/lib/authorization';

type NavLabelKey =
    | 'nav.calendar'
    | 'nav.services'
    | 'nav.customers'
    | 'nav.settings'
    | 'nav.brand'
    | 'nav.bookingPreferences';

type NavLink = {
    href: string;
    labelKey: NavLabelKey;
    permission?: PermissionName;
};

type NavItem = NavLink & {
    icon: LucideIcon;
    children?: readonly NavLink[];
};

const BRAND_SETTINGS_HREF = '/settings/business';

const navItems: NavItem[] = [
    { href: '/calendar', labelKey: 'nav.calendar', icon: Calendar },
    { href: '/services', labelKey: 'nav.services', icon: ListChecks, permission: 'view_services' },
    { href: '/customers', labelKey: 'nav.customers', icon: Users, permission: 'view_customers' },
    {
        href: BRAND_SETTINGS_HREF,
        labelKey: 'nav.settings',
        icon: Settings,
        permission: 'view_business_settings',
        children: [
            {
                href: BRAND_SETTINGS_HREF,
                labelKey: 'nav.brand',
                permission: 'view_business_settings',
            },
            {
                href: '/settings/booking',
                labelKey: 'nav.bookingPreferences',
                permission: 'view_business_settings',
            },
        ],
    },
];

function isActive(url: string, href: string): boolean {
    return url === href || url.startsWith(`${href}/`) || url.startsWith(`${href}?`);
}

type NavItemEntryProps = {
    item: NavItem;
    subItems: readonly NavLink[];
    url: string;
};

function NavItemEntry({ item, subItems, url }: NavItemEntryProps) {
    const { t } = useTranslation('admin');

    const label = t(item.labelKey);
    const Icon = item.icon;
    const isItemActive =
        isActive(url, item.href) || subItems.some((subItem) => isActive(url, subItem.href));

    return (
        <SidebarMenuItem>
            <SidebarMenuButton
                asChild
                isActive={isItemActive}
                tooltip={label}
                className="h-11 md:h-8"
            >
                <Link href={item.href}>
                    <Icon />
                    <span>{label}</span>
                </Link>
            </SidebarMenuButton>

            {subItems.length > 0 ? (
                <SidebarMenuSub>
                    {subItems.map((subItem) => (
                        <SidebarMenuSubItem key={subItem.href}>
                            <SidebarMenuSubButton
                                asChild
                                isActive={isActive(url, subItem.href)}
                                className="h-11 md:h-7"
                            >
                                <Link href={subItem.href}>
                                    <span>{t(subItem.labelKey)}</span>
                                </Link>
                            </SidebarMenuSubButton>
                        </SidebarMenuSubItem>
                    ))}
                </SidebarMenuSub>
            ) : null}
        </SidebarMenuItem>
    );
}

export function AdminNav() {
    const { url } = usePage();
    const { can } = useAuthorization();

    function isPermitted(link: NavLink): boolean {
        return link.permission === undefined || can(link.permission);
    }

    return (
        <SidebarGroup>
            <SidebarGroupContent>
                <SidebarMenu>
                    {navItems.filter(isPermitted).map((item) => (
                        <NavItemEntry
                            key={item.href}
                            item={item}
                            subItems={(item.children ?? []).filter(isPermitted)}
                            url={url}
                        />
                    ))}
                </SidebarMenu>
            </SidebarGroupContent>
        </SidebarGroup>
    );
}

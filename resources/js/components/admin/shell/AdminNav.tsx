import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    Blocks,
    Calendar,
    ChevronRight,
    ListChecks,
    Settings,
    Users,
    type LucideIcon,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useAuthorization } from '@/hooks/use-authorization';
import type { PermissionName } from '@/lib/authorization';

type NavLabelKey =
    | 'nav.calendar'
    | 'nav.services'
    | 'nav.customers'
    | 'nav.integrations'
    | 'nav.settings'
    | 'nav.brand'
    | 'nav.yourProfile'
    | 'nav.team'
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
        href: '/integrations',
        labelKey: 'nav.integrations',
        icon: Blocks,
        permission: 'manage_integrations',
    },
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
                href: '/settings/profile',
                labelKey: 'nav.yourProfile',
                permission: 'view_business_settings',
            },
            {
                href: '/settings/team',
                labelKey: 'nav.team',
                permission: 'view_staff_members',
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

type NavLinkEntryProps = {
    item: NavItem;
    url: string;
};

function NavLinkEntry({ item, url }: NavLinkEntryProps) {
    const { t } = useTranslation('admin');

    const label = t(item.labelKey);
    const Icon = item.icon;

    return (
        <SidebarMenuItem>
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
}

type NavCollapsibleEntryProps = {
    item: NavItem;
    subItems: readonly NavLink[];
    url: string;
};

function NavCollapsibleEntry({ item, subItems, url }: NavCollapsibleEntryProps) {
    const { t } = useTranslation('admin');
    const { isMobile, state, setOpen: setSidebarOpen } = useSidebar();

    const label = t(item.labelKey);
    const Icon = item.icon;
    const isAnyChildActive = subItems.some((subItem) => isActive(url, subItem.href));
    const [isOpen, setIsOpen] = useState(isAnyChildActive);
    const isIconCollapsed = state === 'collapsed' && !isMobile;

    function handleOpenChange(nextOpen: boolean) {
        if (isIconCollapsed) {
            setSidebarOpen(true);
            setIsOpen(true);

            return;
        }

        setIsOpen(nextOpen);
    }

    return (
        <Collapsible
            asChild
            open={isOpen}
            onOpenChange={handleOpenChange}
            className="group/collapsible"
        >
            <SidebarMenuItem>
                <CollapsibleTrigger asChild>
                    <SidebarMenuButton
                        isActive={isAnyChildActive}
                        tooltip={label}
                        className="h-11 md:h-8"
                    >
                        <Icon />
                        <span>{label}</span>
                        <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90 motion-reduce:transition-none" />
                    </SidebarMenuButton>
                </CollapsibleTrigger>

                <CollapsibleContent>
                    <SidebarMenuSub>
                        {subItems.map((subItem) => (
                            <NavSubLink key={subItem.href} link={subItem} url={url} />
                        ))}
                    </SidebarMenuSub>
                </CollapsibleContent>
            </SidebarMenuItem>
        </Collapsible>
    );
}

type NavSubLinkProps = {
    link: NavLink;
    url: string;
};

function NavSubLink({ link, url }: NavSubLinkProps) {
    const { t } = useTranslation('admin');

    const isCurrent = isActive(url, link.href);

    return (
        <SidebarMenuSubItem>
            <SidebarMenuSubButton asChild isActive={isCurrent} className="h-11 md:h-7">
                <Link href={link.href} aria-current={isCurrent ? 'page' : undefined}>
                    <span>{t(link.labelKey)}</span>
                </Link>
            </SidebarMenuSubButton>
        </SidebarMenuSubItem>
    );
}

export function AdminNav() {
    const { url } = usePage();
    const { can } = useAuthorization();

    function isPermitted(link: NavLink): boolean {
        return link.permission === undefined || can(link.permission);
    }

    function renderEntry(item: NavItem) {
        const subItems = (item.children ?? []).filter(isPermitted);

        if (subItems.length === 0) {
            return <NavLinkEntry key={item.href} item={item} url={url} />;
        }

        return <NavCollapsibleEntry key={item.href} item={item} subItems={subItems} url={url} />;
    }

    return (
        <SidebarGroup>
            <SidebarGroupContent>
                <SidebarMenu>{navItems.filter(isPermitted).map(renderEntry)}</SidebarMenu>
            </SidebarGroupContent>
        </SidebarGroup>
    );
}

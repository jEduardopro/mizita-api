import { Link, usePage } from '@inertiajs/react';
import { Calendar, ListChecks, Users, type LucideIcon } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';

type NavItem = {
    href: string;
    labelKey: 'nav.calendar' | 'nav.services' | 'nav.customers';
    icon: LucideIcon;
};

const navItems: NavItem[] = [
    { href: '/calendar', labelKey: 'nav.calendar', icon: Calendar },
    { href: '/services', labelKey: 'nav.services', icon: ListChecks },
    { href: '/customers', labelKey: 'nav.customers', icon: Users },
];

function isActive(url: string, href: string): boolean {
    return url === href || url.startsWith(`${href}/`) || url.startsWith(`${href}?`);
}

export function AdminNav() {
    const { t } = useTranslation('admin');
    const { url } = usePage();

    return (
        <SidebarGroup>
            <SidebarGroupContent>
                <SidebarMenu>
                    {navItems.map((item) => {
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

import { Link, usePage } from '@inertiajs/react';
import { Building2, type LucideIcon } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { PLATFORM_BUSINESSES_URL } from '@/lib/platform-urls';

type NavItem = {
    href: string;
    labelKey: 'nav.businesses';
    icon: LucideIcon;
};

const navItems: readonly NavItem[] = [
    { href: PLATFORM_BUSINESSES_URL, labelKey: 'nav.businesses', icon: Building2 },
];

function isActive(url: string, href: string): boolean {
    return url === href || url.startsWith(`${href}/`) || url.startsWith(`${href}?`);
}

export function PlatformNav() {
    const { t } = useTranslation('platform');
    const { url } = usePage();

    return (
        <SidebarGroup>
            <SidebarGroupContent>
                <SidebarMenu>
                    {navItems.map((item) => {
                        const label = t(item.labelKey);
                        const isCurrent = isActive(url, item.href);
                        const Icon = item.icon;

                        return (
                            <SidebarMenuItem key={item.href}>
                                <SidebarMenuButton
                                    asChild
                                    isActive={isCurrent}
                                    tooltip={label}
                                    className="h-11 md:h-8"
                                >
                                    <Link href={item.href} aria-current={isCurrent ? 'page' : undefined}>
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

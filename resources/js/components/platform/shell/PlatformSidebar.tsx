import { useTranslation } from 'react-i18next';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarRail,
} from '@/components/ui/sidebar';
import { PlatformAccountMenu } from './PlatformAccountMenu';
import { PlatformBrand } from './PlatformBrand';
import { PlatformNav } from './PlatformNav';

export function PlatformSidebar() {
    const { t } = useTranslation('platform');

    return (
        <Sidebar collapsible="icon">
            <SidebarHeader>
                <PlatformBrand />
            </SidebarHeader>

            <SidebarContent>
                <PlatformNav />
            </SidebarContent>

            <SidebarFooter>
                <PlatformAccountMenu />
            </SidebarFooter>

            <SidebarRail aria-label={t('shell.toggleSidebar')} title={t('shell.toggleSidebar')} />
        </Sidebar>
    );
}

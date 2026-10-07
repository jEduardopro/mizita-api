import { useTranslation } from 'react-i18next';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarRail,
} from '@/components/ui/sidebar';
import { AccountMenu } from './AccountMenu';
import { AdminNav } from './AdminNav';
import { BusinessSwitcher } from './BusinessSwitcher';
import { ShareBookingPageEntry } from './ShareBookingPageEntry';

export function AppSidebar() {
    const { t } = useTranslation('admin');

    return (
        <Sidebar collapsible="icon">
            <SidebarHeader>
                <BusinessSwitcher />
            </SidebarHeader>

            <SidebarContent>
                <AdminNav />
            </SidebarContent>

            <SidebarFooter>
                <ShareBookingPageEntry />
                <AccountMenu />
            </SidebarFooter>

            <SidebarRail aria-label={t('shell.toggleSidebar')} title={t('shell.toggleSidebar')} />
        </Sidebar>
    );
}

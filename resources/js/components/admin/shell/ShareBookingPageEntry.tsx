import { Share } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { ShareBookingPageDialog } from '@/domains/businesses/components/ShareBookingPageDialog';
import { useCurrentBusiness } from '@/domains/businesses/queries';

export function ShareBookingPageEntry() {
    const { t } = useTranslation('admin');
    const { data: business } = useCurrentBusiness();
    const [isOpen, setIsOpen] = useState(false);

    if (business === undefined) {
        return null;
    }

    const label = t('shell.shareBookingPage.trigger');

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <ShareBookingPageDialog
                    open={isOpen}
                    onOpenChange={setIsOpen}
                    name={business.name}
                    slug={business.slug}
                >
                    <SidebarMenuButton tooltip={label} className="h-11 cursor-pointer md:h-8">
                        <Share />
                        <span>{label}</span>
                    </SidebarMenuButton>
                </ShareBookingPageDialog>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}

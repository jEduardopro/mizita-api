import { useTranslation } from 'react-i18next';
import { PlanUpgradeNotice } from '@/components/admin/PlanUpgradeNotice';
import { useAuthorization } from '@/hooks/use-authorization';
import { usePlan } from '@/hooks/use-plan';
import type { Service } from '../types';
import { ManagedStaffServicesTab } from './ManagedStaffServicesTab';
import { staffServiceBookingUrl } from './service-urls';
import { StaffServiceLinkButton } from './StaffServiceLinkButton';
import { StaffServicesTab } from './StaffServicesTab';

type Props = {
    staffMemberId: string;
    staffName: string;
    staffBookingUrl: string | null;
    isOwner: boolean;
    onAssignmentsChange?: () => void;
};

export function StaffServicesSection({
    staffMemberId,
    staffName,
    staffBookingUrl,
    isOwner,
    onAssignmentsChange,
}: Props) {
    const { t } = useTranslation('admin');
    const { can } = useAuthorization();
    const { includes } = usePlan();

    const renderLinkAction = (service: Service) => (
        <StaffServiceLinkButton
            serviceName={service.name}
            url={staffBookingUrl === null ? null : staffServiceBookingUrl(staffBookingUrl, service.slug)}
            staffName={staffName}
        />
    );

    if (! includes('team') && ! isOwner) {
        return (
            <div className="grid max-w-2xl gap-4">
                <PlanUpgradeNotice description={t('plan.services.staffPickerHidden')} />

                <StaffServicesTab staffMemberId={staffMemberId} renderLinkAction={renderLinkAction} />
            </div>
        );
    }

    if (can('edit_service')) {
        return (
            <ManagedStaffServicesTab
                staffMemberId={staffMemberId}
                renderLinkAction={renderLinkAction}
                onAssignmentsChange={onAssignmentsChange}
            />
        );
    }

    return <StaffServicesTab staffMemberId={staffMemberId} renderLinkAction={renderLinkAction} />;
}

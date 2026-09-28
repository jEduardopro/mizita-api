import { useAuthorization } from '@/hooks/use-authorization';
import type { Service } from '../types';
import { ManagedStaffServicesTab } from './ManagedStaffServicesTab';
import { staffServiceBookingUrl } from './service-urls';
import { StaffServiceLinkButton } from './StaffServiceLinkButton';
import { StaffServicesTab } from './StaffServicesTab';

type Props = {
    staffMemberId: string;
    staffName: string;
    staffBookingUrl: string | null;
    onAssignmentsChange?: () => void;
};

export function StaffServicesSection({ staffMemberId, staffName, staffBookingUrl, onAssignmentsChange }: Props) {
    const { can } = useAuthorization();

    const renderLinkAction = (service: Service) => (
        <StaffServiceLinkButton
            serviceName={service.name}
            url={staffBookingUrl === null ? null : staffServiceBookingUrl(staffBookingUrl, service.slug)}
            staffName={staffName}
        />
    );

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

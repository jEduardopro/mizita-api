import { useAuthorization } from '@/hooks/use-authorization';
import { ManagedStaffServicesTab } from './ManagedStaffServicesTab';
import { StaffServicesTab } from './StaffServicesTab';

type Props = {
    staffMemberId: string;
};

export function StaffServicesSection({ staffMemberId }: Props) {
    const { can } = useAuthorization();

    if (can('edit_service')) {
        return <ManagedStaffServicesTab staffMemberId={staffMemberId} />;
    }

    return <StaffServicesTab staffMemberId={staffMemberId} />;
}

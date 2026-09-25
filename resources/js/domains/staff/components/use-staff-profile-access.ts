import { useAuthorization } from '@/hooks/use-authorization';
import { useIsCurrentStaffMember } from './use-is-current-staff-member';

export type StaffProfileAccess = 'self' | 'manage' | 'view';

export function useStaffProfileAccess(staffMemberId: string): StaffProfileAccess | undefined {
    const isCurrentStaffMember = useIsCurrentStaffMember(staffMemberId);
    const { can } = useAuthorization();

    if (isCurrentStaffMember === undefined) {
        return undefined;
    }

    if (isCurrentStaffMember) {
        return 'self';
    }

    return can('edit_staff_member') ? 'manage' : 'view';
}

export function canEditStaffProfile(access: StaffProfileAccess | undefined): boolean {
    return access === 'self' || access === 'manage';
}

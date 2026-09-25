import { useMyProfile } from '../queries';

export function useIsCurrentStaffMember(staffMemberId: string): boolean | undefined {
    const { data, isError } = useMyProfile();

    if (data !== undefined) {
        return data.staff_member_id === staffMemberId;
    }

    return isError ? false : undefined;
}

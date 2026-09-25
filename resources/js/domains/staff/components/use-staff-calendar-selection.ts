import { useCallback, useState } from 'react';
import { useAuthorization } from '@/hooks/use-authorization';
import { useMyProfile, useStaffMembers } from '../queries';
import type { StaffMember } from '../types';

const MINIMUM_MEMBERS_TO_CHOOSE_FROM = 2;

export type AvailableStaffCalendarSelection = {
    available: true;
    isResolving: false;
    ownStaffMember: StaffMember;
    teamMembers: StaffMember[];
    selectedStaffMember: StaffMember;
    isOwnCalendarSelected: boolean;
    select: (staffMemberId: string) => void;
};

type UnavailableStaffCalendarSelection = {
    available: false;
    isResolving: boolean;
};

export type StaffCalendarSelection = AvailableStaffCalendarSelection | UnavailableStaffCalendarSelection;

export function useStaffCalendarSelection(): StaffCalendarSelection {
    const { can } = useAuthorization();
    const canManageAllCalendars = can('manage_all_calendars');
    const staffMembers = useStaffMembers();
    const myProfile = useMyProfile();
    const [chosenStaffMemberId, setChosenStaffMemberId] = useState<string | null>(null);

    const select = useCallback((staffMemberId: string) => setChosenStaffMemberId(staffMemberId), []);

    if (! canManageAllCalendars) {
        return { available: false, isResolving: false };
    }

    if (staffMembers.isPending || myProfile.isPending) {
        return { available: false, isResolving: true };
    }

    const members = staffMembers.data ?? [];
    const ownStaffMemberId = myProfile.data?.staff_member_id;
    const ownStaffMember = members.find((member) => member.id === ownStaffMemberId);

    if (ownStaffMember === undefined || members.length < MINIMUM_MEMBERS_TO_CHOOSE_FROM) {
        return { available: false, isResolving: false };
    }

    const selectedStaffMember = members.find((member) => member.id === chosenStaffMemberId) ?? ownStaffMember;

    return {
        available: true,
        isResolving: false,
        ownStaffMember,
        teamMembers: members.filter((member) => member.id !== ownStaffMember.id),
        selectedStaffMember,
        isOwnCalendarSelected: selectedStaffMember.id === ownStaffMember.id,
        select,
    };
}

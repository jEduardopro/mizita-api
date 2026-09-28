import { useCurrentUser } from '@/hooks/use-current-user';
import { useIsTeamMemberPaused, type TeamMemberPauseCheck } from '@/hooks/use-is-team-member-paused';
import { useBookableStaffMembers } from '../queries';
import type { BookableStaffMember } from '../types';

export type AppointmentStaffOption = {
    id: string;
    name: string;
    paused: boolean;
};

export type AppointmentStaffOptions = {
    options: AppointmentStaffOption[];
    defaultStaffMemberId: string | undefined;
    isPending: boolean;
};

function offeredOptions(
    members: BookableStaffMember[],
    assignedStaffMemberId: string | null,
    isPaused: TeamMemberPauseCheck,
): AppointmentStaffOption[] {
    return members
        .filter((member) => ! isPaused(member.role) || member.id === assignedStaffMemberId)
        .map((member) => ({ id: member.id, name: member.name, paused: isPaused(member.role) }));
}

function defaultMember(
    members: BookableStaffMember[],
    currentUserEmail: string,
    isPaused: TeamMemberPauseCheck,
): BookableStaffMember | undefined {
    const ownMember = members.find((member) => member.email === currentUserEmail);

    if (ownMember === undefined || ! isPaused(ownMember.role)) {
        return ownMember;
    }

    return members.find((member) => member.role === 'owner');
}

export function useAppointmentStaffOptions(assignedStaffMemberId: string | null): AppointmentStaffOptions {
    const { data: staffMembers, isPending } = useBookableStaffMembers();
    const { data: currentUser } = useCurrentUser();
    const isPaused = useIsTeamMemberPaused();
    const members = staffMembers ?? [];

    return {
        options: offeredOptions(members, assignedStaffMemberId, isPaused),
        defaultStaffMemberId:
            staffMembers === undefined || currentUser === undefined
                ? undefined
                : defaultMember(members, currentUser.email, isPaused)?.id,
        isPending,
    };
}

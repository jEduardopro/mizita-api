import type { PublicBusinessPage, PublicService, PublicTeamMember } from '../../types';
import { withPinsApplied, type BookingSelection } from './booking-steps';

export type BookingResolution = {
    selection: BookingSelection;
    pinnedMember: PublicTeamMember | null;
    services: PublicService[];
    service: PublicService | null;
    staffMember: PublicTeamMember | null;
};

function teamMemberIn(page: PublicBusinessPage, staffId: string | null): PublicTeamMember | null {
    return page.team.find((member) => member.id === staffId) ?? null;
}

function servicesOfferedBy(
    page: PublicBusinessPage,
    member: PublicTeamMember | null,
): PublicService[] {
    if (member === null) {
        return page.services;
    }

    return page.services.filter((service) => service.staff_ids.includes(member.id));
}

function staffMemberIn(
    page: PublicBusinessPage,
    service: PublicService | null,
    staffId: string | null,
): PublicTeamMember | null {
    if (service === null || staffId === null || ! service.staff_ids.includes(staffId)) {
        return null;
    }

    return teamMemberIn(page, staffId);
}

export function resolveBooking(
    page: PublicBusinessPage,
    requested: BookingSelection,
): BookingResolution {
    const pinnedMember = teamMemberIn(page, requested.with);
    const pinned = withPinsApplied({ ...requested, with: pinnedMember?.id ?? null });

    const services = servicesOfferedBy(page, pinnedMember);
    const service = services.find((candidate) => candidate.id === pinned.service) ?? null;
    const staffMember = staffMemberIn(page, service, pinned.staff);

    return {
        selection: {
            with: pinnedMember?.id ?? null,
            service: service?.id ?? null,
            staff: staffMember?.id ?? null,
            at: requested.at,
        },
        pinnedMember,
        services,
        service,
        staffMember,
    };
}

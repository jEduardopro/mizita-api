import type { AssignableStaffRole, StaffProfileDetails, TeamMember } from '../types';
import { isAssignableStaffRole } from './profile-role';

export function staffProfileDetailsFrom(member: TeamMember): StaffProfileDetails {
    return {
        staff_member_id: member.id,
        name: member.name,
        email: member.email,
        job_title: member.job_title,
        about: member.about,
        phone: member.phone,
        photo_url: member.photo_url,
        role: member.level,
        booking_slug: member.booking_slug,
        booking_url: member.booking_url,
        booking_link_blockers: member.booking_link_blockers,
    };
}

export function editableLevelOf(member: TeamMember): AssignableStaffRole | undefined {
    return isAssignableStaffRole(member.level) ? member.level : undefined;
}

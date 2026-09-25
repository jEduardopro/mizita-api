import type { AssignableStaffRole, StaffProfileDetails, TeamMember } from '../types';
import { isAssignableStaffRole } from './profile-role';

export function staffProfileDetailsFrom(member: TeamMember): StaffProfileDetails {
    return {
        name: member.name,
        email: member.email,
        job_title: member.job_title,
        about: member.about,
        phone: member.phone,
        photo_url: member.photo_url,
        role: member.level,
    };
}

export function editableLevelOf(member: TeamMember): AssignableStaffRole | undefined {
    return isAssignableStaffRole(member.level) ? member.level : undefined;
}

import { ASSIGNABLE_STAFF_ROLES, type AssignableStaffRole, type StaffRole } from '../types';

export const ROLE_LABEL_KEYS = {
    owner: 'team.levels.owner.label',
    staff: 'team.levels.staff.label',
    no_access: 'team.levels.noAccess.label',
} as const satisfies Record<StaffRole, string>;

export const ROLE_DESCRIPTION_KEYS = {
    staff: 'team.levels.staff.description',
    no_access: 'team.levels.noAccess.description',
} as const satisfies Record<AssignableStaffRole, string>;

export function isAssignableStaffRole(role: StaffRole): role is AssignableStaffRole {
    return ASSIGNABLE_STAFF_ROLES.some((assignable) => assignable === role);
}

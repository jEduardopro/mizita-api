import type { StaffRole } from '../types';

export const ROLE_LABEL_KEYS = {
    owner: 'profile.roles.owner',
    staff: 'profile.roles.staff',
} as const satisfies Record<StaffRole, string>;

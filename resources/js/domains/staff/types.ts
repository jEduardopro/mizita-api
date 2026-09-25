import type { PhoneCountryCode } from '@/lib/phone';

export type StaffRole = 'owner' | 'staff' | 'no_access';

export type StaffMember = {
    id: string;
    name: string;
    email: string;
    role: StaffRole;
    photo_url: string | null;
};

export const PROFILE_NAME_MAX_LENGTH = 255;

export const PROFILE_JOB_TITLE_MAX_LENGTH = 120;

export const PROFILE_ABOUT_MAX_LENGTH = 1000;

export const PROFILE_PHONE_MAX_LENGTH = 24;

export const PROFILE_PHOTO_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'] as const;

export const PROFILE_PHOTO_MAXIMUM_BYTES = 2 * 1024 * 1024;

export const ASSIGNABLE_STAFF_ROLES = ['staff', 'no_access'] as const;

export type AssignableStaffRole = (typeof ASSIGNABLE_STAFF_ROLES)[number];

export const DEFAULT_ASSIGNABLE_STAFF_ROLE: AssignableStaffRole = 'staff';

export const TEAM_SORT_FIELDS = ['name', 'created_at'] as const;

export type TeamSortField = (typeof TEAM_SORT_FIELDS)[number];

export const TEAM_INVITATION_MAXIMUM_MEMBERS = 20;

export const TEAM_MEMBER_EMAIL_MAX_LENGTH = 255;

export type ProfilePhone = {
    country_code: string;
    national_number: string;
    e164: string;
};

export type MyProfile = {
    id: string;
    staff_member_id: string;
    name: string;
    email: string;
    job_title: string | null;
    about: string | null;
    phone: ProfilePhone | null;
    photo_url: string | null;
    role: StaffRole;
    has_password: boolean;
};

export type ProfilePhonePayload = {
    country_code: PhoneCountryCode;
    national_number: string;
};

export type UpdateMyProfilePayload = {
    name: string;
    job_title: string | null;
    about: string | null;
    phone: ProfilePhonePayload | null;
};

export type TeamMember = {
    id: string;
    name: string;
    email: string;
    phone: ProfilePhone | null;
    photo_url: string | null;
    job_title: string | null;
    about: string | null;
    level: StaffRole;
    invitation_pending: boolean;
    temporary_password_available: boolean;
    created_at: string;
};

export type TemporaryPassword = {
    temporary_password: string;
};

export const TEMPORARY_PASSWORD_UNAVAILABLE_CODE = 'temporary_password_unavailable';

export type TeamMemberRemovalBlocker = 'owner' | 'upcoming_appointments';

export type TeamMemberRemoval = {
    removable: boolean;
    blocker: TeamMemberRemovalBlocker | null;
};

export const TEAM_MEMBER_HAS_UPCOMING_APPOINTMENTS_CODE = 'team_member_has_upcoming_appointments';

export type TeamListParams = {
    page: number;
    per_page: number;
    sort: TeamSortField;
    direction: 'asc' | 'desc';
    search?: string;
};

export type TeamInvitee = {
    name: string;
    email: string;
    level: AssignableStaffRole;
};

export type InviteTeamMembersPayload = {
    members: TeamInvitee[];
};

export type UpdateTeamMemberPayload = UpdateMyProfilePayload & {
    level?: AssignableStaffRole;
};

export type StaffProfileDetails = Pick<
    MyProfile,
    'name' | 'email' | 'job_title' | 'about' | 'phone' | 'photo_url' | 'role'
>;

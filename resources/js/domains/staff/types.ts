import type { PhoneCountryCode } from '@/lib/phone';

export type StaffMember = {
    id: string;
    name: string;
    email: string;
};

export const PROFILE_NAME_MAX_LENGTH = 255;

export const PROFILE_JOB_TITLE_MAX_LENGTH = 120;

export const PROFILE_ABOUT_MAX_LENGTH = 1000;

export const PROFILE_PHONE_MAX_LENGTH = 24;

export const PROFILE_PHOTO_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'] as const;

export const PROFILE_PHOTO_MAXIMUM_BYTES = 2 * 1024 * 1024;

export type StaffRole = 'owner' | 'staff';

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

export type StaffProfileDetails = Pick<
    MyProfile,
    'name' | 'email' | 'job_title' | 'about' | 'phone' | 'photo_url' | 'role'
>;

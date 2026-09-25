import { SUPPORTED_PHONE_COUNTRIES, type PhoneCountryCode } from '@/lib/phone';
import type { AssignableStaffRole, MyProfile, ProfilePhonePayload, UpdateTeamMemberPayload } from '../types';

const DEFAULT_PHONE_COUNTRY: PhoneCountryCode = 'MX';

export type ProfileField = 'name' | 'phoneCountry' | 'phoneNumber' | 'jobTitle' | 'about' | 'level';

export const profileServerFields: Record<ProfileField, string> = {
    name: 'name',
    phoneCountry: 'phone.country_code',
    phoneNumber: 'phone.national_number',
    jobTitle: 'job_title',
    about: 'about',
    level: 'level',
};

export type ProfileFormValues = {
    name: string;
    phoneCountry: PhoneCountryCode;
    phoneNumber: string;
    jobTitle: string;
    about: string;
    level: AssignableStaffRole | null;
};

export type ProfileFormSource = Pick<MyProfile, 'name' | 'job_title' | 'about' | 'phone'>;

const PROFILE_FIELDS: readonly ProfileField[] = [
    'name',
    'phoneCountry',
    'phoneNumber',
    'jobTitle',
    'about',
    'level',
];

function phoneCountryOf(profile: ProfileFormSource): PhoneCountryCode {
    const supported = SUPPORTED_PHONE_COUNTRIES.find(
        (country) => country.code === profile.phone?.country_code,
    );

    return supported?.code ?? DEFAULT_PHONE_COUNTRY;
}

export function profileValuesFrom(
    profile: ProfileFormSource,
    editableLevel: AssignableStaffRole | undefined,
): ProfileFormValues {
    return {
        name: profile.name,
        phoneCountry: phoneCountryOf(profile),
        phoneNumber: profile.phone?.national_number ?? '',
        jobTitle: profile.job_title ?? '',
        about: profile.about ?? '',
        level: editableLevel ?? null,
    };
}

function trimmedOrNull(value: string): string | null {
    const trimmed = value.trim();

    return trimmed === '' ? null : trimmed;
}

function phoneFrom(values: ProfileFormValues): ProfilePhonePayload | null {
    const nationalNumber = values.phoneNumber.trim();

    return nationalNumber === ''
        ? null
        : { country_code: values.phoneCountry, national_number: nationalNumber };
}

export function profilePayloadFrom(values: ProfileFormValues): UpdateTeamMemberPayload {
    const payload = {
        name: values.name.trim(),
        job_title: trimmedOrNull(values.jobTitle),
        about: trimmedOrNull(values.about),
        phone: phoneFrom(values),
    };

    return values.level === null ? payload : { ...payload, level: values.level };
}

export function sameProfileValues(first: ProfileFormValues, second: ProfileFormValues): boolean {
    return PROFILE_FIELDS.every((field) => first[field] === second[field]);
}

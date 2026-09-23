import type {
    ContactFieldLevel,
    ContactFieldName,
    ContactFieldsSettings,
} from '@/domains/businesses/types';

export const CONTACT_FIELD_NAMES = [
    'phone',
    'email',
    'address',
] as const satisfies readonly ContactFieldName[];

export const DEFAULT_CONTACT_FIELDS: ContactFieldsSettings = {
    phone: 'required',
    email: 'optional',
    address: 'hidden',
};

export const CONTACT_FIELD_LABEL_KEYS = {
    phone: 'bookingPreferences.contactFields.fields.phone',
    email: 'bookingPreferences.contactFields.fields.email',
    address: 'bookingPreferences.contactFields.fields.address',
} as const satisfies Record<ContactFieldName, string>;

export function isContactFieldShown(level: ContactFieldLevel): boolean {
    return level !== 'hidden';
}

export function isContactFieldRequired(level: ContactFieldLevel): boolean {
    return level === 'required';
}

export function levelForShownSwitch(isShown: boolean): ContactFieldLevel {
    return isShown ? 'optional' : 'hidden';
}

export function levelForRequiredSwitch(isRequired: boolean): ContactFieldLevel {
    return isRequired ? 'required' : 'optional';
}

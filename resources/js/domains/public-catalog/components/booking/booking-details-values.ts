import type { DialCodeOption } from '@/components/form/DialCodePicker';
import { SUPPORTED_PHONE_COUNTRIES, type PhoneCountryCode } from '@/lib/phone';
import {
    CONTACT_FIELD_NAMES,
    type ContactFieldLevel,
    type ContactFieldName,
    type PublicContactFields,
    type PublicGuestPayload,
} from '../../types';
import { isContactFieldRequired, isContactFieldShown } from './contact-field-levels';

const DEFAULT_PHONE_COUNTRY: PhoneCountryCode = 'MX';

export type BookingDetailsValues = {
    name: string;
    email: string;
    phoneCountry: PhoneCountryCode;
    phoneNumber: string;
    street: string;
    city: string;
    state: string;
    postalCode: string;
    notes: string;
};

export type BookingDetailsField = keyof BookingDetailsValues;

export const BOOKING_ADDRESS_FIELDS = ['street', 'city', 'state', 'postalCode'] as const;

export type BookingAddressField = (typeof BOOKING_ADDRESS_FIELDS)[number];

export type BookingAddressValues = Pick<BookingDetailsValues, BookingAddressField>;

export const EMPTY_BOOKING_DETAILS: BookingDetailsValues = {
    name: '',
    email: '',
    phoneCountry: DEFAULT_PHONE_COUNTRY,
    phoneNumber: '',
    street: '',
    city: '',
    state: '',
    postalCode: '',
    notes: '',
};

export const BOOKING_DETAILS_SERVER_FIELDS: Record<BookingDetailsField, string> = {
    name: 'guest.name',
    email: 'guest.email',
    phoneCountry: 'guest.phone.country_code',
    phoneNumber: 'guest.phone.national_number',
    street: 'guest.address.street',
    city: 'guest.address.city',
    state: 'guest.address.state',
    postalCode: 'guest.address.postal_code',
    notes: 'notes',
};

const CONTACT_FIELD_INPUTS: Record<ContactFieldName, readonly BookingDetailsField[]> = {
    phone: ['phoneNumber'],
    email: ['email'],
    address: BOOKING_ADDRESS_FIELDS,
};

type GuestContact = Omit<PublicGuestPayload, 'name'>;

function trimmedOrNull(value: string): string | null {
    const trimmed = value.trim();

    return trimmed === '' ? null : trimmed;
}

function isBlank(value: string): boolean {
    return trimmedOrNull(value) === null;
}

export function phoneCountryOptions(locale: string): DialCodeOption[] {
    const regionNames = new Intl.DisplayNames([locale], { type: 'region' });

    return SUPPORTED_PHONE_COUNTRIES.map((country) => ({
        code: country.code,
        name: regionNames.of(country.code) ?? country.code,
        dialCode: country.dialCode,
    }));
}

export function supportedPhoneCountry(code: string): PhoneCountryCode | null {
    return SUPPORTED_PHONE_COUNTRIES.find((country) => country.code === code)?.code ?? null;
}

export function missingRequiredFields(
    values: BookingDetailsValues,
    contactFields: PublicContactFields,
): BookingDetailsField[] {
    return CONTACT_FIELD_NAMES.filter((name) => isContactFieldRequired(contactFields[name]))
        .flatMap((name) => CONTACT_FIELD_INPUTS[name])
        .filter((field) => isBlank(values[field]));
}

function emailFrom(values: BookingDetailsValues, level: ContactFieldLevel): GuestContact {
    const email = trimmedOrNull(values.email);

    if (! isContactFieldShown(level) || email === null) {
        return {};
    }

    return { email };
}

function phoneFrom(values: BookingDetailsValues, level: ContactFieldLevel): GuestContact {
    const nationalNumber = trimmedOrNull(values.phoneNumber);

    if (! isContactFieldShown(level) || nationalNumber === null) {
        return {};
    }

    return { phone: { country_code: values.phoneCountry, national_number: nationalNumber } };
}

function addressFrom(values: BookingDetailsValues, level: ContactFieldLevel): GuestContact {
    const isEmpty = BOOKING_ADDRESS_FIELDS.every((field) => isBlank(values[field]));

    if (! isContactFieldShown(level) || isEmpty) {
        return {};
    }

    return {
        address: {
            street: values.street.trim(),
            city: values.city.trim(),
            state: values.state.trim(),
            postal_code: values.postalCode.trim(),
        },
    };
}

export function guestFrom(
    values: BookingDetailsValues,
    contactFields: PublicContactFields,
): PublicGuestPayload {
    return {
        name: values.name.trim(),
        ...emailFrom(values, contactFields.email),
        ...phoneFrom(values, contactFields.phone),
        ...addressFrom(values, contactFields.address),
    };
}

export function notesFrom(values: BookingDetailsValues): string | null {
    return trimmedOrNull(values.notes);
}

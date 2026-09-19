import type { DialCodeOption } from '@/components/form/DialCodePicker';
import { SUPPORTED_PHONE_COUNTRIES, type PhoneCountryCode } from '@/lib/phone';
import type { PublicGuestPayload } from '../../types';

const DEFAULT_PHONE_COUNTRY: PhoneCountryCode = 'MX';

export type BookingDetailsValues = {
    name: string;
    email: string;
    phoneCountry: PhoneCountryCode;
    phoneNumber: string;
    notes: string;
};

export type BookingDetailsField = keyof BookingDetailsValues;

export const EMPTY_BOOKING_DETAILS: BookingDetailsValues = {
    name: '',
    email: '',
    phoneCountry: DEFAULT_PHONE_COUNTRY,
    phoneNumber: '',
    notes: '',
};

export const BOOKING_DETAILS_SERVER_FIELDS: Record<BookingDetailsField, string> = {
    name: 'guest.name',
    email: 'guest.email',
    phoneCountry: 'guest.phone.country_code',
    phoneNumber: 'guest.phone.national_number',
    notes: 'notes',
};

function trimmedOrNull(value: string): string | null {
    const trimmed = value.trim();

    return trimmed === '' ? null : trimmed;
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

export function hasContactDetails(values: BookingDetailsValues): boolean {
    return trimmedOrNull(values.email) !== null || trimmedOrNull(values.phoneNumber) !== null;
}

export function guestFrom(values: BookingDetailsValues): PublicGuestPayload {
    const nationalNumber = trimmedOrNull(values.phoneNumber);

    return {
        name: values.name.trim(),
        email: trimmedOrNull(values.email),
        phone:
            nationalNumber === null
                ? null
                : { country_code: values.phoneCountry, national_number: nationalNumber },
    };
}

export function notesFrom(values: BookingDetailsValues): string | null {
    return trimmedOrNull(values.notes);
}

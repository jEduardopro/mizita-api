import type { PhoneCountryCode } from '@/lib/phone';

export const CUSTOMER_SORT_FIELDS = ['name', 'created_at'] as const;

export type CustomerSortField = (typeof CUSTOMER_SORT_FIELDS)[number];

export const CUSTOMER_NAME_MAX_LENGTH = 120;

export const CUSTOMER_EMAIL_MAX_LENGTH = 254;

export const CUSTOMER_NOTES_MAX_LENGTH = 2000;

export const CUSTOMER_PHONE_MAX_LENGTH = 24;

export const CUSTOMER_STREET_MAX_LENGTH = 160;

export const CUSTOMER_CITY_MAX_LENGTH = 120;

export const CUSTOMER_POSTAL_CODE_MAX_LENGTH = 12;

export const CUSTOMER_PHOTO_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'] as const;

export const CUSTOMER_PHOTO_MAXIMUM_BYTES = 2 * 1024 * 1024;

export type CustomerPhone = {
    country_code: string;
    national_number: string;
};

export type CustomerAddress = {
    street: string;
    city: string | null;
    state_id: string | null;
    postal_code: string | null;
    country_code: string;
};

export type Customer = {
    id: string;
    name: string;
    email: string | null;
    phone: CustomerPhone | null;
    birth_date: string | null;
    notes: string | null;
    address: CustomerAddress | null;
    photo_url: string | null;
    created_at: string;
};

export type CustomerListParams = {
    page: number;
    per_page: number;
    sort: CustomerSortField;
    direction: 'asc' | 'desc';
    search?: string;
};

export type CustomerPhonePayload = {
    country_code: PhoneCountryCode;
    national_number: string;
};

export type CustomerAddressPayload = {
    street: string;
    city: string | null;
    state_id: string | null;
    postal_code: string | null;
    country_code: string;
};

export type CustomerPayload = {
    name: string;
    email: string | null;
    phone: CustomerPhonePayload | null;
    birth_date: string | null;
    notes: string | null;
    address: CustomerAddressPayload | null;
};

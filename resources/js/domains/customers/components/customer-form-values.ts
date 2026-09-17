import { SUPPORTED_PHONE_COUNTRIES, type PhoneCountryCode } from '@/lib/phone';
import type { Customer, CustomerAddressPayload, CustomerPayload, CustomerPhonePayload } from '../types';

const DEFAULT_PHONE_COUNTRY: PhoneCountryCode = 'MX';

export const CUSTOMER_COUNTRY_CODE = 'MX';

export type CustomerField =
    | 'name'
    | 'email'
    | 'phoneCountry'
    | 'phoneNumber'
    | 'birthDate'
    | 'notes'
    | 'street'
    | 'city'
    | 'stateId'
    | 'postalCode';

export const serverFields: Record<CustomerField, string> = {
    name: 'name',
    email: 'email',
    phoneCountry: 'phone.country_code',
    phoneNumber: 'phone.national_number',
    birthDate: 'birth_date',
    notes: 'notes',
    street: 'address.street',
    city: 'address.city',
    stateId: 'address.state_id',
    postalCode: 'address.postal_code',
};

export type CustomerFormValues = {
    name: string;
    email: string;
    phoneCountry: PhoneCountryCode;
    phoneNumber: string;
    birthDate: string;
    notes: string;
    street: string;
    city: string;
    stateId: string | null;
    postalCode: string;
};

function phoneCountryFrom(customer: Customer): PhoneCountryCode {
    const supported = SUPPORTED_PHONE_COUNTRIES.find(
        (country) => country.code === customer.phone?.country_code,
    );

    return supported?.code ?? DEFAULT_PHONE_COUNTRY;
}

export function initialCustomerValues(customer: Customer | null): CustomerFormValues {
    if (customer === null) {
        return {
            name: '',
            email: '',
            phoneCountry: DEFAULT_PHONE_COUNTRY,
            phoneNumber: '',
            birthDate: '',
            notes: '',
            street: '',
            city: '',
            stateId: null,
            postalCode: '',
        };
    }

    return {
        name: customer.name,
        email: customer.email ?? '',
        phoneCountry: phoneCountryFrom(customer),
        phoneNumber: customer.phone?.national_number ?? '',
        birthDate: customer.birth_date ?? '',
        notes: customer.notes ?? '',
        street: customer.address?.street ?? '',
        city: customer.address?.city ?? '',
        stateId: customer.address?.state_id ?? null,
        postalCode: customer.address?.postal_code ?? '',
    };
}

function trimmedOrNull(value: string): string | null {
    const trimmed = value.trim();

    return trimmed === '' ? null : trimmed;
}

function phoneFrom(values: CustomerFormValues): CustomerPhonePayload | null {
    const nationalNumber = values.phoneNumber.trim();

    return nationalNumber === ''
        ? null
        : { country_code: values.phoneCountry, national_number: nationalNumber };
}

function addressFrom(values: CustomerFormValues): CustomerAddressPayload | null {
    const street = values.street.trim();
    const city = trimmedOrNull(values.city);
    const postalCode = trimmedOrNull(values.postalCode);

    if (street === '' && city === null && postalCode === null && values.stateId === null) {
        return null;
    }

    return {
        street,
        city,
        state_id: values.stateId,
        postal_code: postalCode,
        country_code: CUSTOMER_COUNTRY_CODE,
    };
}

export function customerPayloadFrom(values: CustomerFormValues): CustomerPayload {
    return {
        name: values.name.trim(),
        email: trimmedOrNull(values.email),
        phone: phoneFrom(values),
        birth_date: trimmedOrNull(values.birthDate),
        notes: trimmedOrNull(values.notes),
        address: addressFrom(values),
    };
}

import { SUPPORTED_PHONE_COUNTRIES } from '@/lib/phone';
import type { CustomerPhone } from '../types';

export function formatPhone(phone: CustomerPhone | null): string | null {
    if (phone === null) {
        return null;
    }

    const country = SUPPORTED_PHONE_COUNTRIES.find(
        (supported) => supported.code === phone.country_code,
    );

    return country === undefined
        ? phone.national_number
        : `${country.dialCode} ${phone.national_number}`;
}

export function contactSummary(customer: { email: string | null; phone: CustomerPhone | null }): string[] {
    return [customer.email, formatPhone(customer.phone)].filter(
        (detail): detail is string => detail !== null,
    );
}

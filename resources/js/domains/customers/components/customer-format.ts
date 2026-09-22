import { formatPhoneNumber } from '@/lib/phone';
import type { CustomerPhone } from '../types';

export function formatPhone(phone: CustomerPhone | null): string | null {
    if (phone === null) {
        return null;
    }

    return formatPhoneNumber(phone);
}

export function contactSummary(customer: { email: string | null; phone: CustomerPhone | null }): string[] {
    return [customer.email, formatPhone(customer.phone)].filter(
        (detail): detail is string => detail !== null,
    );
}

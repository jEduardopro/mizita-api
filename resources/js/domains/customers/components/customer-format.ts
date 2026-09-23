import { formatPhoneNumber } from '@/lib/phone';
import type { CustomerAddress, CustomerPhone } from '../types';

const LOCALITY_SEPARATOR = ' · ';

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

export function addressStateName(address: CustomerAddress, catalogStateName: string | null): string | null {
    return catalogStateName ?? address.state_name;
}

export function addressLines(address: CustomerAddress, stateName: string | null): string[] {
    const locality = [address.city, stateName, address.postal_code]
        .filter((part): part is string => part !== null && part !== '')
        .join(LOCALITY_SEPARATOR);

    return [address.street, locality].filter((line) => line !== '');
}

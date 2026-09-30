import type { CustomerListFilters, CustomerRegistrationRange } from '../types';

export function customerListFilters(
    search: string,
    registrationRange: CustomerRegistrationRange | null,
): CustomerListFilters {
    return {
        search: search === '' ? undefined : search,
        created_from: registrationRange?.from,
        created_to: registrationRange?.to,
    };
}

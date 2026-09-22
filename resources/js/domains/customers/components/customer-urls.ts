export const CUSTOMERS_URL = '/customers';

export const NEW_CUSTOMER_URL = '/customers/new';

export function customerShowUrl(id: string): string {
    return `${CUSTOMERS_URL}/${id}`;
}

export function customerEditUrl(id: string): string {
    return `${CUSTOMERS_URL}/${id}/edit`;
}

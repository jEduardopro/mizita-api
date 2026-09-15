export const SERVICES_URL = '/services';

export const NEW_SERVICE_URL = '/services/new';

export function serviceEditUrl(id: string): string {
    return `${SERVICES_URL}/${id}/edit`;
}

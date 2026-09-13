import { api } from '@/lib/api';
import type { Business, BusinessNameAvailability, CreateBusinessPayload } from './types';

/**
 * The only place that names a Businesses URL or unwraps the `data` envelope.
 */
export async function checkBusinessNameAvailability(
    name: string,
    signal?: AbortSignal,
): Promise<BusinessNameAvailability> {
    const { data } = await api.get<{ data: BusinessNameAvailability }>('/businesses/availability', {
        params: { name },
        signal,
    });

    return data.data;
}

/** `POST /businesses`, the controller's `store`. Answers 201. */
export async function createBusiness(payload: CreateBusinessPayload): Promise<Business> {
    const { data } = await api.post<{ data: Business }>('/businesses', payload);

    return data.data;
}

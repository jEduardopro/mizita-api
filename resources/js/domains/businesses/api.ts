import { api } from '@/lib/api';
import type { Business, BusinessNameAvailability, CreateBusinessPayload } from './types';

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

export async function createBusiness(payload: CreateBusinessPayload): Promise<Business> {
    const { data } = await api.post<{ data: Business }>('/businesses', payload);

    return data.data;
}

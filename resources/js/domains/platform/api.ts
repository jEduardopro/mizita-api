import { api } from '@/lib/api';
import type { Paginated } from '@/types/api';
import type { PlatformBusiness, PlatformBusinessListParams } from './types';

export async function listPlatformBusinesses(
    params: PlatformBusinessListParams,
    signal?: AbortSignal,
): Promise<Paginated<PlatformBusiness>> {
    const { data } = await api.get<Paginated<PlatformBusiness>>('/platform/businesses', {
        params,
        signal,
    });

    return data;
}

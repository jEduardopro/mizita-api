import { api } from '@/lib/api';
import type { Industry } from './types';

/**
 * The only place that names an Industries URL or knows about the `data`
 * envelope. Everything above this file works with plain, typed rows.
 */
export async function listIndustries(signal?: AbortSignal): Promise<Industry[]> {
    const { data } = await api.get<{ data: Industry[] }>('/industries', { signal });

    return data.data;
}

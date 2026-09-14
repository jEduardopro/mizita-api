import { api } from '@/lib/api';
import type { Industry } from './types';

export async function listIndustries(signal?: AbortSignal): Promise<Industry[]> {
    const { data } = await api.get<{ data: Industry[] }>('/industries', { signal });

    return data.data;
}

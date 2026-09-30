import { api } from '@/lib/api';
import type { BusinessStatistics, StatisticsRange } from './types';

export async function getStatistics(
    range: StatisticsRange | null,
    signal?: AbortSignal,
): Promise<BusinessStatistics> {
    const { data } = await api.get<{ data: BusinessStatistics }>('/statistics', {
        params: range ?? undefined,
        signal,
    });

    return data.data;
}

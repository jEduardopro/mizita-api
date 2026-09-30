import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { getStatistics } from './api';
import type { StatisticsRange } from './types';

export const statisticsKeys = {
    all: ['statistics'] as const,
    summary: (range: StatisticsRange | null) => [...statisticsKeys.all, 'summary', range] as const,
};

export function useStatistics(range: StatisticsRange | null) {
    return useQuery({
        queryKey: statisticsKeys.summary(range),
        queryFn: ({ signal }) => getStatistics(range, signal),
        placeholderData: keepPreviousData,
    });
}

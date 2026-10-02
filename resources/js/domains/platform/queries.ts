import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { listPlatformBusinesses } from './api';
import type { PlatformBusinessListParams } from './types';

export const platformKeys = {
    all: ['platform'] as const,
    businesses: () => [...platformKeys.all, 'businesses'] as const,
    businessList: (params: PlatformBusinessListParams) =>
        [...platformKeys.businesses(), 'list', params] as const,
};

export function usePlatformBusinesses(params: PlatformBusinessListParams) {
    return useQuery({
        queryKey: platformKeys.businessList(params),
        queryFn: ({ signal }) => listPlatformBusinesses(params, signal),
        placeholderData: keepPreviousData,
    });
}

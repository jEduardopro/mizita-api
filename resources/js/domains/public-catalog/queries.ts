import { useQuery } from '@tanstack/react-query';
import { fetchPublicBusinessPage } from './api';

export const publicCatalogKeys = {
    all: ['public-catalog'] as const,
    businesses: () => [...publicCatalogKeys.all, 'businesses'] as const,
    business: (slug: string) => [...publicCatalogKeys.businesses(), slug] as const,
};

export function usePublicBusinessPage(slug: string) {
    return useQuery({
        queryKey: publicCatalogKeys.business(slug),
        queryFn: ({ signal }) => fetchPublicBusinessPage(slug, signal),
    });
}

import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useCallback } from 'react';
import {
    cancelPublicBooking,
    createPublicBooking,
    fetchPublicAvailability,
    fetchPublicBooking,
    fetchPublicBusinessPage,
    reschedulePublicBooking,
} from './api';
import type {
    CreatePublicBookingPayload,
    PublicAvailabilityQuery,
    PublicBooking,
    PublicBookingCredentials,
    ReschedulePublicBookingPayload,
} from './types';

const AVAILABILITY_LIFETIME_MS = 30 * 1000;

const EMPTY_AVAILABILITY_QUERY: PublicAvailabilityQuery = {
    service_id: '',
    staff_id: '',
    from: '',
    to: '',
};

export const publicCatalogKeys = {
    all: ['public-catalog'] as const,
    businesses: () => [...publicCatalogKeys.all, 'businesses'] as const,
    business: (slug: string) => [...publicCatalogKeys.businesses(), slug] as const,
    availabilities: (slug: string) => [...publicCatalogKeys.business(slug), 'availability'] as const,
    availability: (slug: string, query: PublicAvailabilityQuery) =>
        [...publicCatalogKeys.availabilities(slug), query] as const,
    bookings: (slug: string) => [...publicCatalogKeys.business(slug), 'bookings'] as const,
    booking: (slug: string, reference: string) =>
        [...publicCatalogKeys.bookings(slug), reference] as const,
};

export function usePublicBusinessPage(slug: string) {
    return useQuery({
        queryKey: publicCatalogKeys.business(slug),
        queryFn: ({ signal }) => fetchPublicBusinessPage(slug, signal),
    });
}

export function usePublicAvailability(slug: string, query: PublicAvailabilityQuery | null) {
    return useQuery({
        queryKey: publicCatalogKeys.availability(slug, query ?? EMPTY_AVAILABILITY_QUERY),
        queryFn: ({ signal }) =>
            fetchPublicAvailability(slug, query ?? EMPTY_AVAILABILITY_QUERY, signal),
        enabled: query !== null,
        placeholderData: keepPreviousData,
        staleTime: AVAILABILITY_LIFETIME_MS,
    });
}

export function usePublicBooking(slug: string, credentials: PublicBookingCredentials) {
    return useQuery({
        queryKey: publicCatalogKeys.booking(slug, credentials.reference),
        queryFn: ({ signal }) => fetchPublicBooking(slug, credentials, signal),
        enabled: credentials.manageToken !== '',
        retry: false,
    });
}

function useBookingCacheRefresh(slug: string) {
    const queryClient = useQueryClient();

    return useCallback(
        (booking: PublicBooking) => {
            queryClient.setQueryData(
                publicCatalogKeys.booking(slug, booking.reference_code),
                booking,
            );

            void queryClient.invalidateQueries({
                queryKey: publicCatalogKeys.availabilities(slug),
            });
        },
        [queryClient, slug],
    );
}

export function useCreatePublicBooking(slug: string) {
    const refreshBooking = useBookingCacheRefresh(slug);

    return useMutation({
        mutationFn: (payload: CreatePublicBookingPayload) => createPublicBooking(slug, payload),
        onSuccess: (confirmation) => refreshBooking(confirmation.booking),
    });
}

export function useReschedulePublicBooking(slug: string, credentials: PublicBookingCredentials) {
    const refreshBooking = useBookingCacheRefresh(slug);

    return useMutation({
        mutationFn: (payload: ReschedulePublicBookingPayload) =>
            reschedulePublicBooking(slug, credentials, payload),
        onSuccess: refreshBooking,
    });
}

export function useCancelPublicBooking(slug: string, credentials: PublicBookingCredentials) {
    const refreshBooking = useBookingCacheRefresh(slug);

    return useMutation({
        mutationFn: () => cancelPublicBooking(slug, credentials),
        onSuccess: refreshBooking,
    });
}

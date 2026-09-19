import { useCallback } from 'react';
import { useUrlQueryState } from '@/hooks/use-url-query-state';
import { isNotFoundError } from '@/lib/http';
import { usePublicBooking, usePublicBusinessPage } from '../../queries';
import type { PublicBooking, PublicBookingCredentials, PublicBusinessPage } from '../../types';
import { MANAGE_TOKEN_PARAM } from './booking-steps';

export type PendingBookingRecord = {
    status: 'loading' | 'invalid' | 'error';
    retry(): void;
};

export type ReadyBookingRecord = {
    status: 'ready';
    page: PublicBusinessPage;
    booking: PublicBooking;
    credentials: PublicBookingCredentials;
};

export type BookingRecord = PendingBookingRecord | ReadyBookingRecord;

export function useBookingRecord(slug: string, reference: string): BookingRecord {
    const { read } = useUrlQueryState();
    const manageToken = read(MANAGE_TOKEN_PARAM) ?? '';
    const credentials: PublicBookingCredentials = { reference, manageToken };

    const business = usePublicBusinessPage(slug);
    const booking = usePublicBooking(slug, credentials);

    const refetchBusiness = business.refetch;
    const refetchBooking = booking.refetch;

    const retry = useCallback(() => {
        void refetchBusiness();
        void refetchBooking();
    }, [refetchBooking, refetchBusiness]);

    if (manageToken === '' || (booking.isError && isNotFoundError(booking.error))) {
        return { status: 'invalid', retry };
    }

    if (business.isError || booking.isError) {
        return { status: 'error', retry };
    }

    if (business.isPending || booking.isPending) {
        return { status: 'loading', retry };
    }

    return {
        status: 'ready',
        page: business.data,
        booking: booking.data,
        credentials,
    };
}

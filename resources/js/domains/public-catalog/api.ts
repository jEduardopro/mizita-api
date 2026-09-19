import { api } from '@/lib/api';
import type {
    CreatePublicBookingPayload,
    PublicAvailabilityQuery,
    PublicAvailableDay,
    PublicBooking,
    PublicBookingConfirmation,
    PublicBookingCredentials,
    PublicBusinessPage,
    ReschedulePublicBookingPayload,
} from './types';

const MANAGE_TOKEN_HEADER = 'X-Manage-Token';

function businessUrl(slug: string): string {
    return `/public/businesses/${encodeURIComponent(slug)}`;
}

function bookingUrl(slug: string, reference: string): string {
    return `${businessUrl(slug)}/bookings/${encodeURIComponent(reference)}`;
}

function manageHeaders(manageToken: string): Record<string, string> {
    return { [MANAGE_TOKEN_HEADER]: manageToken };
}

export async function fetchPublicBusinessPage(
    slug: string,
    signal?: AbortSignal,
): Promise<PublicBusinessPage> {
    const { data } = await api.get<{ data: PublicBusinessPage }>(businessUrl(slug), { signal });

    return data.data;
}

export async function fetchPublicAvailability(
    slug: string,
    query: PublicAvailabilityQuery,
    signal?: AbortSignal,
): Promise<PublicAvailableDay[]> {
    const { data } = await api.get<{ data: PublicAvailableDay[] }>(
        `${businessUrl(slug)}/availability`,
        { params: query, signal },
    );

    return data.data;
}

export async function createPublicBooking(
    slug: string,
    payload: CreatePublicBookingPayload,
): Promise<PublicBookingConfirmation> {
    const { data } = await api.post<{ data: PublicBookingConfirmation }>(
        `${businessUrl(slug)}/bookings`,
        payload,
    );

    return data.data;
}

export async function fetchPublicBooking(
    slug: string,
    credentials: PublicBookingCredentials,
    signal?: AbortSignal,
): Promise<PublicBooking> {
    const { data } = await api.get<{ data: PublicBooking }>(
        bookingUrl(slug, credentials.reference),
        { headers: manageHeaders(credentials.manageToken), signal },
    );

    return data.data;
}

export async function reschedulePublicBooking(
    slug: string,
    credentials: PublicBookingCredentials,
    payload: ReschedulePublicBookingPayload,
): Promise<PublicBooking> {
    const { data } = await api.patch<{ data: PublicBooking }>(
        bookingUrl(slug, credentials.reference),
        payload,
        { headers: manageHeaders(credentials.manageToken) },
    );

    return data.data;
}

export async function cancelPublicBooking(
    slug: string,
    credentials: PublicBookingCredentials,
): Promise<PublicBooking> {
    const { data } = await api.delete<{ data: PublicBooking }>(
        bookingUrl(slug, credentials.reference),
        { headers: manageHeaders(credentials.manageToken) },
    );

    return data.data;
}

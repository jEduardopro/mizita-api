import { api } from '@/lib/api';
import type {
    BookingPage,
    Business,
    BusinessNameAvailability,
    BusinessSettings,
    CalendarSettings,
    CreateBusinessPayload,
    ReorderGalleryPayload,
    UpdateBusinessSettingsPayload,
} from './types';

const IMAGE_FIELD = 'image';

export async function checkBusinessNameAvailability(
    name: string,
    signal?: AbortSignal,
): Promise<BusinessNameAvailability> {
    const { data } = await api.get<{ data: BusinessNameAvailability }>('/businesses/availability', {
        params: { name },
        signal,
    });

    return data.data;
}

export async function fetchMyBusinesses(signal?: AbortSignal): Promise<Business[]> {
    const { data } = await api.get<{ data: Business[] }>('/me/businesses', { signal });

    return data.data;
}

export async function createBusiness(payload: CreateBusinessPayload): Promise<Business> {
    const { data } = await api.post<{ data: Business }>('/businesses', payload);

    return data.data;
}

export async function getBusinessSettings(signal?: AbortSignal): Promise<BusinessSettings> {
    const { data } = await api.get<{ data: BusinessSettings }>('/business/settings', { signal });

    return data.data;
}

export async function getCalendarSettings(signal?: AbortSignal): Promise<CalendarSettings> {
    const { data } = await api.get<{ data: CalendarSettings }>('/business/calendar-settings', {
        signal,
    });

    return data.data;
}

export async function updateBusinessSettings(
    payload: UpdateBusinessSettingsPayload,
): Promise<BusinessSettings> {
    const { data } = await api.patch<{ data: BusinessSettings }>('/business/settings', payload);

    return data.data;
}

export async function attachBusinessLogo(logo: File): Promise<BusinessSettings> {
    const body = new FormData();

    body.append('logo', logo);

    const { data } = await api.post<{ data: BusinessSettings }>('/business/logo', body);

    return data.data;
}

export async function removeBusinessLogo(): Promise<BusinessSettings> {
    const { data } = await api.delete<{ data: BusinessSettings }>('/business/logo');

    return data.data;
}

export async function attachBookingPageBanner(banner: File): Promise<BookingPage> {
    const body = new FormData();

    body.append(IMAGE_FIELD, banner);

    const { data } = await api.post<{ data: BookingPage }>('/booking-page/banner', body);

    return data.data;
}

export async function removeBookingPageBanner(): Promise<BookingPage> {
    const { data } = await api.delete<{ data: BookingPage }>('/booking-page/banner');

    return data.data;
}

export async function addGalleryImage(image: File): Promise<BookingPage> {
    const body = new FormData();

    body.append(IMAGE_FIELD, image);

    const { data } = await api.post<{ data: BookingPage }>('/booking-page/gallery', body);

    return data.data;
}

export async function removeGalleryImage(imageId: string): Promise<BookingPage> {
    const { data } = await api.delete<{ data: BookingPage }>(`/booking-page/gallery/${imageId}`);

    return data.data;
}

export async function reorderGallery(imageIds: string[]): Promise<BookingPage> {
    const payload: ReorderGalleryPayload = { images: imageIds };

    const { data } = await api.patch<{ data: BookingPage }>('/booking-page/gallery/order', payload);

    return data.data;
}

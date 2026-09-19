import { api } from '@/lib/api';
import type { Paginated } from '@/types/api';
import type {
    Appointment,
    AppointmentPayload,
    AppointmentRange,
    BookableCustomer,
    BookableService,
    BookableStaffMember,
} from './types';

const BOOKABLE_LOOKUP_PARAMS = { page: 1, per_page: 100, sort: 'name', direction: 'asc' } as const;

const CUSTOMER_SEARCH_PARAMS = { page: 1, per_page: 8, sort: 'name', direction: 'asc' } as const;

export async function listAppointments(
    range: AppointmentRange,
    signal?: AbortSignal,
): Promise<Appointment[]> {
    const { data } = await api.get<{ data: Appointment[] }>('/appointments', {
        params: range,
        signal,
    });

    return data.data;
}

export async function getAppointment(id: string, signal?: AbortSignal): Promise<Appointment> {
    const { data } = await api.get<{ data: Appointment }>(`/appointments/${id}`, { signal });

    return data.data;
}

export async function createAppointment(payload: AppointmentPayload): Promise<Appointment> {
    const { data } = await api.post<{ data: Appointment }>('/appointments', payload);

    return data.data;
}

export async function updateAppointment(
    id: string,
    payload: AppointmentPayload,
): Promise<Appointment> {
    const { data } = await api.put<{ data: Appointment }>(`/appointments/${id}`, payload);

    return data.data;
}

export async function deleteAppointment(id: string): Promise<void> {
    await api.delete(`/appointments/${id}`);
}

export async function listBookableServices(signal?: AbortSignal): Promise<BookableService[]> {
    const { data } = await api.get<Paginated<BookableService>>('/services', {
        params: BOOKABLE_LOOKUP_PARAMS,
        signal,
    });

    return data.data;
}

export async function listBookableStaffMembers(signal?: AbortSignal): Promise<BookableStaffMember[]> {
    const { data } = await api.get<{ data: BookableStaffMember[] }>('/staff', { signal });

    return data.data;
}

export async function searchBookableCustomers(
    query: string,
    signal?: AbortSignal,
): Promise<BookableCustomer[]> {
    const { data } = await api.get<Paginated<BookableCustomer>>('/customers', {
        params: { ...CUSTOMER_SEARCH_PARAMS, search: query === '' ? undefined : query },
        signal,
    });

    return data.data;
}

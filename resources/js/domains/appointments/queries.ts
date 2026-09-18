import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    createAppointment,
    createBookableCustomer,
    deleteAppointment,
    getAppointment,
    listAppointments,
    listBookableServices,
    listBookableStaffMembers,
    searchBookableCustomers,
    updateAppointment,
} from './api';
import type { AppointmentPayload, AppointmentRange } from './types';

const STAFF_DIRECTORY_LIFETIME_MS = 5 * 60 * 1000;

export const appointmentKeys = {
    all: ['appointments'] as const,
    range: (range: AppointmentRange) => [...appointmentKeys.all, 'range', range] as const,
    detail: (id: string) => [...appointmentKeys.all, 'detail', id] as const,
    bookableServices: () => [...appointmentKeys.all, 'bookable-services'] as const,
    bookableStaff: () => [...appointmentKeys.all, 'bookable-staff'] as const,
    bookableCustomers: (query: string) => [...appointmentKeys.all, 'bookable-customers', query] as const,
};

export function useAppointments(range: AppointmentRange) {
    return useQuery({
        queryKey: appointmentKeys.range(range),
        queryFn: ({ signal }) => listAppointments(range, signal),
        placeholderData: keepPreviousData,
    });
}

export function useAppointment(id: string) {
    return useQuery({
        queryKey: appointmentKeys.detail(id),
        queryFn: ({ signal }) => getAppointment(id, signal),
    });
}

function useAppointmentMutation<TVariables, TData>(
    mutationFn: (variables: TVariables) => Promise<TData>,
) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn,
        onSuccess: () => queryClient.invalidateQueries({ queryKey: appointmentKeys.all }),
    });
}

export function useCreateAppointment() {
    return useAppointmentMutation((payload: AppointmentPayload) => createAppointment(payload));
}

export function useUpdateAppointment() {
    return useAppointmentMutation(({ id, payload }: { id: string; payload: AppointmentPayload }) =>
        updateAppointment(id, payload),
    );
}

export function useDeleteAppointment() {
    return useAppointmentMutation((id: string) => deleteAppointment(id));
}

export function useBookableServices() {
    return useQuery({
        queryKey: appointmentKeys.bookableServices(),
        queryFn: ({ signal }) => listBookableServices(signal),
    });
}

export function useBookableStaffMembers() {
    return useQuery({
        queryKey: appointmentKeys.bookableStaff(),
        queryFn: ({ signal }) => listBookableStaffMembers(signal),
        staleTime: STAFF_DIRECTORY_LIFETIME_MS,
    });
}

export function useBookableCustomerSearch(query: string) {
    return useQuery({
        queryKey: appointmentKeys.bookableCustomers(query),
        queryFn: ({ signal }) => searchBookableCustomers(query, signal),
    });
}

export function useCreateBookableCustomer() {
    return useMutation({
        mutationFn: (name: string) => createBookableCustomer(name),
    });
}

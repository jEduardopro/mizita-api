import {
    keepPreviousData,
    useInfiniteQuery,
    useMutation,
    useQuery,
    useQueryClient,
} from '@tanstack/react-query';
import { useCallback } from 'react';
import {
    cancelAppointment,
    createAppointment,
    deleteAppointment,
    getAppointment,
    listAppointments,
    listBookableServices,
    listBookableStaffMembers,
    listCustomerAppointments,
    searchBookableCustomers,
    updateAppointment,
} from './api';
import type { AppointmentPayload, AppointmentRange } from './types';

const STAFF_DIRECTORY_LIFETIME_MS = 5 * 60 * 1000;

const CUSTOMER_HISTORY_PAGE_SIZE = 20;

const FIRST_PAGE = 1;

export const appointmentKeys = {
    all: ['appointments'] as const,
    range: (range: AppointmentRange) => [...appointmentKeys.all, 'range', range] as const,
    detail: (id: string) => [...appointmentKeys.all, 'detail', id] as const,
    forCustomer: (customerId: string) => [...appointmentKeys.all, 'customer', customerId] as const,
    bookableServices: () => [...appointmentKeys.all, 'bookable-services'] as const,
    bookableStaff: () => [...appointmentKeys.all, 'bookable-staff'] as const,
    bookableCustomers: () => [...appointmentKeys.all, 'bookable-customers'] as const,
    bookableCustomerSearch: (query: string) =>
        [...appointmentKeys.bookableCustomers(), query] as const,
};

export function useAppointments(range: AppointmentRange) {
    return useQuery({
        queryKey: appointmentKeys.range(range),
        queryFn: ({ signal }) => listAppointments(range, signal),
        placeholderData: keepPreviousData,
    });
}

export function useInfiniteCustomerAppointments(customerId: string) {
    return useInfiniteQuery({
        queryKey: appointmentKeys.forCustomer(customerId),
        queryFn: ({ pageParam, signal }) =>
            listCustomerAppointments(
                customerId,
                { page: pageParam, per_page: CUSTOMER_HISTORY_PAGE_SIZE },
                signal,
            ),
        initialPageParam: FIRST_PAGE,
        getNextPageParam: (lastPage) =>
            lastPage.meta.current_page < lastPage.meta.last_page
                ? lastPage.meta.current_page + 1
                : undefined,
    });
}

export function useAppointment(id: string) {
    return useQuery({
        queryKey: appointmentKeys.detail(id),
        queryFn: ({ signal }) => getAppointment(id, signal),
    });
}

export function useRefreshAppointments() {
    const queryClient = useQueryClient();

    return useCallback(() => {
        void queryClient.invalidateQueries({ queryKey: appointmentKeys.all });
    }, [queryClient]);
}

function useAppointmentMutation<TVariables, TData>(
    mutationFn: (variables: TVariables) => Promise<TData>,
) {
    const invalidateAppointments = useRefreshAppointments();

    return useMutation({ mutationFn, onSuccess: invalidateAppointments });
}

export function useCreateAppointment() {
    return useAppointmentMutation((payload: AppointmentPayload) => createAppointment(payload));
}

export function useUpdateAppointment() {
    return useAppointmentMutation(({ id, payload }: { id: string; payload: AppointmentPayload }) =>
        updateAppointment(id, payload),
    );
}

export function useCancelAppointment() {
    const invalidateAppointments = useRefreshAppointments();

    return useMutation({
        mutationFn: (id: string) => cancelAppointment(id),
        onSettled: invalidateAppointments,
    });
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
        queryKey: appointmentKeys.bookableCustomerSearch(query),
        queryFn: ({ signal }) => searchBookableCustomers(query, signal),
    });
}

export function useRefreshBookableCustomers() {
    const queryClient = useQueryClient();

    return useCallback(() => {
        void queryClient.invalidateQueries({ queryKey: appointmentKeys.bookableCustomers() });
    }, [queryClient]);
}

import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    attachCustomerPhoto,
    createCustomer,
    deleteCustomer,
    getCustomer,
    listCustomers,
    removeCustomerPhoto,
    updateCustomer,
} from './api';
import type { CustomerListParams, CustomerPayload } from './types';

export const customerKeys = {
    all: ['customers'] as const,
    list: (params: CustomerListParams) => [...customerKeys.all, 'list', params] as const,
    detail: (id: string) => [...customerKeys.all, 'detail', id] as const,
};

export function useCustomers(params: CustomerListParams) {
    return useQuery({
        queryKey: customerKeys.list(params),
        queryFn: ({ signal }) => listCustomers(params, signal),
        placeholderData: keepPreviousData,
    });
}

export function useCustomer(id: string) {
    return useQuery({
        queryKey: customerKeys.detail(id),
        queryFn: ({ signal }) => getCustomer(id, signal),
    });
}

function useCustomerMutation<TVariables, TData>(
    mutationFn: (variables: TVariables) => Promise<TData>,
) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn,
        onSuccess: () => queryClient.invalidateQueries({ queryKey: customerKeys.all }),
    });
}

export function useCreateCustomer() {
    return useCustomerMutation((payload: CustomerPayload) => createCustomer(payload));
}

export function useUpdateCustomer() {
    return useCustomerMutation(({ id, payload }: { id: string; payload: CustomerPayload }) =>
        updateCustomer(id, payload),
    );
}

export function useDeleteCustomer() {
    return useCustomerMutation((id: string) => deleteCustomer(id));
}

export function useAttachCustomerPhoto() {
    return useCustomerMutation(({ id, photo }: { id: string; photo: File }) =>
        attachCustomerPhoto(id, photo),
    );
}

export function useRemoveCustomerPhoto() {
    return useCustomerMutation((id: string) => removeCustomerPhoto(id));
}

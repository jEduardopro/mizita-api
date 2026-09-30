import {
    keepPreviousData,
    useInfiniteQuery,
    useMutation,
    useQuery,
    useQueryClient,
} from '@tanstack/react-query';
import {
    attachCustomerPhoto,
    createCustomer,
    deleteCustomer,
    getCustomer,
    listCustomers,
    removeCustomerPhoto,
    updateCustomer,
} from './api';
import type { CustomerListFilters, CustomerListParams, CustomerPayload } from './types';

const INFINITE_PAGE_SIZE = 20;

const FIRST_PAGE = 1;

export const customerKeys = {
    all: ['customers'] as const,
    list: (params: CustomerListParams) => [...customerKeys.all, 'list', params] as const,
    infinite: (filters: CustomerListFilters) => [...customerKeys.all, 'infinite', filters] as const,
    detail: (id: string) => [...customerKeys.all, 'detail', id] as const,
};

export function useCustomers(params: CustomerListParams) {
    return useQuery({
        queryKey: customerKeys.list(params),
        queryFn: ({ signal }) => listCustomers(params, signal),
        placeholderData: keepPreviousData,
    });
}

export function useInfiniteCustomers(filters: CustomerListFilters) {
    return useInfiniteQuery({
        queryKey: customerKeys.infinite(filters),
        queryFn: ({ pageParam, signal }) =>
            listCustomers(
                {
                    ...filters,
                    page: pageParam,
                    per_page: INFINITE_PAGE_SIZE,
                    sort: 'name',
                    direction: 'asc',
                },
                signal,
            ),
        placeholderData: keepPreviousData,
        initialPageParam: FIRST_PAGE,
        getNextPageParam: (lastPage) =>
            lastPage.meta.current_page < lastPage.meta.last_page
                ? lastPage.meta.current_page + 1
                : undefined,
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

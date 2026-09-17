import {
    keepPreviousData,
    useInfiniteQuery,
    useMutation,
    useQuery,
    useQueryClient,
} from '@tanstack/react-query';
import {
    attachServiceImage,
    createService,
    deleteService,
    duplicateService,
    getService,
    listServices,
    removeServiceImage,
    updateService,
} from './api';
import type { DuplicateServicePayload, ServiceListParams, ServicePayload } from './types';

const INFINITE_PAGE_SIZE = 20;

const FIRST_PAGE = 1;

export const serviceKeys = {
    all: ['services'] as const,
    list: (params: ServiceListParams) => [...serviceKeys.all, 'list', params] as const,
    infinite: (search: string) => [...serviceKeys.all, 'infinite', search] as const,
    detail: (id: string) => [...serviceKeys.all, 'detail', id] as const,
};

export function useServices(params: ServiceListParams) {
    return useQuery({
        queryKey: serviceKeys.list(params),
        queryFn: ({ signal }) => listServices(params, signal),
        placeholderData: keepPreviousData,
    });
}

export function useInfiniteServices(search: string) {
    return useInfiniteQuery({
        queryKey: serviceKeys.infinite(search),
        queryFn: ({ pageParam, signal }) =>
            listServices(
                {
                    page: pageParam,
                    per_page: INFINITE_PAGE_SIZE,
                    sort: 'name',
                    direction: 'asc',
                    search: search === '' ? undefined : search,
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

export function useService(id: string) {
    return useQuery({
        queryKey: serviceKeys.detail(id),
        queryFn: ({ signal }) => getService(id, signal),
    });
}

function useServiceMutation<TVariables, TData>(mutationFn: (variables: TVariables) => Promise<TData>) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn,
        onSuccess: () => queryClient.invalidateQueries({ queryKey: serviceKeys.all }),
    });
}

export function useCreateService() {
    return useServiceMutation((payload: ServicePayload) => createService(payload));
}

export function useUpdateService() {
    return useServiceMutation(({ id, payload }: { id: string; payload: ServicePayload }) =>
        updateService(id, payload),
    );
}

export function useDeleteService() {
    return useServiceMutation((id: string) => deleteService(id));
}

export function useDuplicateService() {
    return useServiceMutation(({ id, payload }: { id: string; payload: DuplicateServicePayload }) =>
        duplicateService(id, payload),
    );
}

export function useAttachServiceImage() {
    return useServiceMutation(({ id, image }: { id: string; image: File }) =>
        attachServiceImage(id, image),
    );
}

export function useRemoveServiceImage() {
    return useServiceMutation((id: string) => removeServiceImage(id));
}

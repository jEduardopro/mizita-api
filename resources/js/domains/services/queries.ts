import {
    keepPreviousData,
    useInfiniteQuery,
    useMutation,
    useQuery,
    useQueryClient,
} from '@tanstack/react-query';
import {
    assignStaffToService,
    attachServiceImage,
    createService,
    deleteService,
    duplicateService,
    getService,
    listServices,
    listStaffServices,
    removeServiceImage,
    unassignStaffFromService,
    updateService,
} from './api';
import type { DuplicateServicePayload, ServiceListParams, ServicePayload } from './types';

const INFINITE_PAGE_SIZE = 20;

const FIRST_PAGE = 1;

const MAXIMUM_PAGE_SIZE = 100;

const ASSIGNABLE_SERVICES_PARAMS: ServiceListParams = {
    page: FIRST_PAGE,
    per_page: MAXIMUM_PAGE_SIZE,
    sort: 'name',
    direction: 'asc',
};

export const serviceKeys = {
    all: ['services'] as const,
    list: (params: ServiceListParams) => [...serviceKeys.all, 'list', params] as const,
    infinite: (search: string) => [...serviceKeys.all, 'infinite', search] as const,
    detail: (id: string) => [...serviceKeys.all, 'detail', id] as const,
    byStaff: (staffMemberId: string) => [...serviceKeys.all, 'staff', staffMemberId] as const,
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

export function useStaffServices(staffMemberId: string) {
    return useQuery({
        queryKey: serviceKeys.byStaff(staffMemberId),
        queryFn: ({ signal }) => listStaffServices(staffMemberId, signal),
    });
}

export function useAssignableServices() {
    return useQuery({
        queryKey: serviceKeys.list(ASSIGNABLE_SERVICES_PARAMS),
        queryFn: ({ signal }) => listServices(ASSIGNABLE_SERVICES_PARAMS, signal),
    });
}

type StaffAssignment = {
    serviceId: string;
    staffMemberId: string;
};

export function useAssignStaffToService() {
    return useServiceMutation(({ serviceId, staffMemberId }: StaffAssignment) =>
        assignStaffToService(serviceId, staffMemberId),
    );
}

export function useUnassignStaffFromService() {
    return useServiceMutation(({ serviceId, staffMemberId }: StaffAssignment) =>
        unassignStaffFromService(serviceId, staffMemberId),
    );
}

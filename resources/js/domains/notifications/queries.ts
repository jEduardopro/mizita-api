import {
    keepPreviousData,
    useInfiniteQuery,
    useMutation,
    useQuery,
    useQueryClient,
} from '@tanstack/react-query';
import {
    getNotification,
    getUnreadNotificationCount,
    listNotifications,
    markNotificationAsRead,
} from './api';
import type {
    NotificationListFilters,
    NotificationListParams,
    NotificationScope,
} from './types';

const UNREAD_POLL_INTERVAL_MS = 60_000;

const FEED_PAGE_SIZE = 20;

const PREVIEW_PAGE_SIZE = 10;

const FIRST_PAGE = 1;

function unreadPreviewParams(scope: NotificationScope): NotificationListParams {
    return {
        scope,
        status: 'unread',
        page: FIRST_PAGE,
        per_page: PREVIEW_PAGE_SIZE,
    };
}

export const notificationKeys = {
    all: ['notifications'] as const,
    list: (params: NotificationListParams) => [...notificationKeys.all, 'list', params] as const,
    infinite: (filters: NotificationListFilters) =>
        [...notificationKeys.all, 'infinite', filters] as const,
    unreadCount: (scope: NotificationScope) =>
        [...notificationKeys.all, 'unread-count', scope] as const,
    detail: (id: string) => [...notificationKeys.all, 'detail', id] as const,
};

export function useUnreadNotificationCount(scope: NotificationScope) {
    return useQuery({
        queryKey: notificationKeys.unreadCount(scope),
        queryFn: ({ signal }) => getUnreadNotificationCount(scope, signal),
        refetchInterval: UNREAD_POLL_INTERVAL_MS,
        refetchOnWindowFocus: true,
    });
}

export function useUnreadNotificationsPreview(scope: NotificationScope) {
    const params = unreadPreviewParams(scope);

    return useQuery({
        queryKey: notificationKeys.list(params),
        queryFn: ({ signal }) => listNotifications(params, signal),
        staleTime: 0,
        refetchInterval: UNREAD_POLL_INTERVAL_MS,
        refetchOnWindowFocus: true,
    });
}

export function useInfiniteNotifications(filters: NotificationListFilters) {
    return useInfiniteQuery({
        queryKey: notificationKeys.infinite(filters),
        queryFn: ({ pageParam, signal }) =>
            listNotifications({ ...filters, page: pageParam, per_page: FEED_PAGE_SIZE }, signal),
        placeholderData: keepPreviousData,
        initialPageParam: FIRST_PAGE,
        getNextPageParam: (lastPage) =>
            lastPage.meta.current_page < lastPage.meta.last_page
                ? lastPage.meta.current_page + 1
                : undefined,
    });
}

export function useNotification(id: string) {
    return useQuery({
        queryKey: notificationKeys.detail(id),
        queryFn: ({ signal }) => getNotification(id, signal),
    });
}

export function useMarkNotificationAsRead() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (id: string) => markNotificationAsRead(id),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: notificationKeys.all }),
    });
}

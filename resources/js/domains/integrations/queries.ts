import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    createGoogleCalendarAuthorization,
    deleteGoogleCalendarConnection,
    listIntegrations,
} from './api';

export const integrationKeys = {
    all: ['integrations'] as const,
    list: () => [...integrationKeys.all, 'list'] as const,
};

export function useIntegrations() {
    return useQuery({
        queryKey: integrationKeys.list(),
        queryFn: ({ signal }) => listIntegrations(signal),
    });
}

export function useStartGoogleCalendarAuthorization() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: createGoogleCalendarAuthorization,
        onError: () => queryClient.invalidateQueries({ queryKey: integrationKeys.list() }),
    });
}

export function useDisconnectGoogleCalendar() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: deleteGoogleCalendarConnection,
        onSettled: () => queryClient.invalidateQueries({ queryKey: integrationKeys.list() }),
    });
}

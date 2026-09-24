import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { getMySchedule, replaceMySchedule } from './api';

export const availabilityKeys = {
    all: ['availability'] as const,
    mySchedule: () => [...availabilityKeys.all, 'me', 'schedule'] as const,
};

export function useMySchedule() {
    return useQuery({
        queryKey: availabilityKeys.mySchedule(),
        queryFn: ({ signal }) => getMySchedule(signal),
    });
}

export function useReplaceMySchedule() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: replaceMySchedule,
        onSuccess: (schedule) => {
            queryClient.setQueryData(availabilityKeys.mySchedule(), schedule);
        },
    });
}

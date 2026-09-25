import { keepPreviousData, useMutation, useQuery, useQueryClient, type QueryKey } from '@tanstack/react-query';
import { getMySchedule, getStaffSchedule, replaceMySchedule, replaceStaffSchedule } from './api';
import type { ReplaceSchedulePayload } from './types';

export const availabilityKeys = {
    all: ['availability'] as const,
    mySchedule: () => [...availabilityKeys.all, 'me', 'schedule'] as const,
    staffSchedules: () => [...availabilityKeys.all, 'staff-member'] as const,
    staffSchedule: (staffMemberId: string) =>
        [...availabilityKeys.staffSchedules(), staffMemberId, 'schedule'] as const,
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

            return queryClient.invalidateQueries({ queryKey: availabilityKeys.staffSchedules() });
        },
    });
}

export function useStaffSchedule(staffMemberId: string) {
    return useQuery({
        queryKey: availabilityKeys.staffSchedule(staffMemberId),
        queryFn: ({ signal }) => getStaffSchedule(staffMemberId, signal),
    });
}

export function useCalendarSchedule(staffMemberId: string | null) {
    const queryKey: QueryKey =
        staffMemberId === null ? availabilityKeys.mySchedule() : availabilityKeys.staffSchedule(staffMemberId);

    return useQuery({
        queryKey,
        queryFn: ({ signal }) =>
            staffMemberId === null ? getMySchedule(signal) : getStaffSchedule(staffMemberId, signal),
        placeholderData: keepPreviousData,
    });
}

export function useReplaceStaffSchedule(staffMemberId: string) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (payload: ReplaceSchedulePayload) => replaceStaffSchedule(staffMemberId, payload),
        onSuccess: (schedule) => {
            queryClient.setQueryData(availabilityKeys.staffSchedule(staffMemberId), schedule);

            return queryClient.invalidateQueries({ queryKey: availabilityKeys.mySchedule() });
        },
    });
}

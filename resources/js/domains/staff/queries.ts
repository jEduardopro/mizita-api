import { useQuery } from '@tanstack/react-query';
import { useCallback, useMemo } from 'react';
import type { CheckboxListOption } from '@/components/form/CheckboxListField';
import { listStaffMembers } from './api';

const DIRECTORY_LIFETIME_MS = 5 * 60 * 1000;

export const staffKeys = {
    all: ['staff'] as const,
    list: () => [...staffKeys.all, 'list'] as const,
};

export function useStaffMembers() {
    return useQuery({
        queryKey: staffKeys.list(),
        queryFn: ({ signal }) => listStaffMembers(signal),
        staleTime: DIRECTORY_LIFETIME_MS,
    });
}

type StaffChoices = {
    options: CheckboxListOption[];
    isPending: boolean;
    isError: boolean;
    refetch: () => void;
};

export function useStaffChoices(): StaffChoices {
    const { data, isPending, isError, refetch } = useStaffMembers();

    const options = useMemo(
        () => (data ?? []).map((member) => ({ value: member.id, label: member.name })),
        [data],
    );

    const retry = useCallback(() => void refetch(), [refetch]);

    return { options, isPending, isError, refetch: retry };
}

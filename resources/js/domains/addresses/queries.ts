import { queryOptions, useQuery } from '@tanstack/react-query';
import { useCallback, useMemo } from 'react';
import type { ComboboxOption } from '@/components/form/use-combobox';
import { listStates } from './api';

const CATALOG_LIFETIME_MS = 24 * 60 * 60 * 1000;

export const stateKeys = {
    all: ['states'] as const,
    list: (country: string) => [...stateKeys.all, 'list', country] as const,
};

function statesQuery(country: string) {
    return queryOptions({
        queryKey: stateKeys.list(country),
        queryFn: ({ signal }) => listStates(country, signal),
        staleTime: CATALOG_LIFETIME_MS,
        gcTime: CATALOG_LIFETIME_MS,
    });
}

export function useStates(country: string) {
    return useQuery(statesQuery(country));
}

export function useStateName(country: string, stateId: string | null): string | null {
    const { data } = useQuery({
        ...statesQuery(country),
        enabled: stateId !== null,
        select: (states) => states.find((state) => state.id === stateId)?.name ?? null,
    });

    return data ?? null;
}

type StateChoices = {
    options: ComboboxOption[];
    isPending: boolean;
    isError: boolean;
    refetch: () => void;
};

export function useStateChoices(country: string): StateChoices {
    const { data, isPending, isError, refetch } = useStates(country);

    const options = useMemo(
        () => (data ?? []).map((state) => ({ value: state.id, label: state.name })),
        [data],
    );

    const retry = useCallback(() => void refetch(), [refetch]);

    return { options, isPending, isError, refetch: retry };
}

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { checkBusinessNameAvailability, createBusiness, fetchMyBusinesses } from './api';

const MIN_NAME_LENGTH_FOR_CHECK = 2;

const NAME_CHECK_DEBOUNCE_MS = 400;

export const businessKeys = {
    all: ['businesses'] as const,
    nameAvailability: () => [...businessKeys.all, 'name-availability'] as const,
    nameAvailabilityFor: (name: string) => [...businessKeys.nameAvailability(), name] as const,
    mine: () => [...businessKeys.all, 'mine'] as const,
};

export function useMyBusinesses() {
    return useQuery({
        queryKey: businessKeys.mine(),
        queryFn: ({ signal }) => fetchMyBusinesses(signal),
    });
}


export type NameStatus = 'idle' | 'checking' | 'available' | 'taken' | 'unknown';

type NameAvailability = {
    status: NameStatus;
    slug: string | null;
};

export function useBusinessNameAvailability(name: string): NameAvailability {
    const trimmed = name.trim();
    const debounced = useDebouncedValue(trimmed, NAME_CHECK_DEBOUNCE_MS);

    const query = useQuery({
        queryKey: businessKeys.nameAvailabilityFor(debounced),
        queryFn: ({ signal }) => checkBusinessNameAvailability(debounced, signal),
        enabled: debounced.length >= MIN_NAME_LENGTH_FOR_CHECK,
    });

    const settled = debounced === trimmed && ! query.isFetching;

    if (trimmed.length < MIN_NAME_LENGTH_FOR_CHECK) {
        return { status: 'idle', slug: null };
    }

    if (! settled) {
        return { status: 'checking', slug: null };
    }

    if (query.isError) {
        return { status: 'unknown', slug: null };
    }

    if (! query.data) {
        return { status: 'checking', slug: null };
    }

    return query.data.available
        ? { status: 'available', slug: query.data.slug }
        : { status: 'taken', slug: null };
}

export function useCreateBusiness() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: createBusiness,
        onSuccess: () => {
            queryClient.removeQueries({ queryKey: businessKeys.nameAvailability() });
        },
    });
}

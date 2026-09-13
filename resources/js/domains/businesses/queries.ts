import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { checkBusinessNameAvailability, createBusiness } from './api';

/** Below this, a name is not yet a question worth asking the server. */
const MIN_NAME_LENGTH_FOR_CHECK = 2;

/** Long enough to be a pause in typing, short enough to feel like an answer. */
const NAME_CHECK_DEBOUNCE_MS = 400;

export const businessKeys = {
    all: ['businesses'] as const,
    /** The whole set of name checks, so they can be dropped in one call. */
    nameAvailability: () => [...businessKeys.all, 'name-availability'] as const,
    nameAvailabilityFor: (name: string) => [...businessKeys.nameAvailability(), name] as const,
};

/**
 * What the field can say about the name currently in the box.
 *
 * `unknown` is a failed question, not a failed name: it renders nothing at all.
 */
export type NameStatus = 'idle' | 'checking' | 'available' | 'taken' | 'unknown';

type NameAvailability = {
    status: NameStatus;
    /** The booking address the name would produce, when it is free. */
    slug: string | null;
};

/**
 * Asks the server whether a name is still free, as it is being typed.
 *
 * The whole hook exists to make one bug impossible: a verdict that describes a
 * name other than the one on screen. `settled` is the guard — the answer is only
 * shown when the debounced name is the typed name *and* nothing is in flight, so
 * every intermediate state collapses to `checking` instead of to a stale
 * `available`. For the same reason there is no `keepPreviousData` here: keeping
 * the previous answer across a key change is precisely the thing being prevented.
 *
 * The key is the trimmed name with its case intact. Slugification is the
 * server's rule and folding case here would be a guess at it; the cost is one
 * redundant request, and the gain is that typing back to a name already asked
 * about answers from cache with no round trip at all.
 */
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

    // The tick between the key changing and the request starting: nothing is in
    // flight yet and there is no answer for this name, which reads as checking
    // rather than as anything about the name itself.
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
            // These lookups are now lies: the name this user just claimed is no
            // longer available, and nothing else reads them. Remove rather than
            // invalidate — no observer is left to refetch for.
            queryClient.removeQueries({ queryKey: businessKeys.nameAvailability() });
        },
    });
}

import { QueryClient } from '@tanstack/react-query';
import { isAxiosError } from 'axios';

/**
 * How many times a query may be retried before it is reported as failed.
 * Only network faults and 5xx ever get that far.
 */
const MAX_RETRIES = 2;

/**
 * A 4xx from this API is a decision, not flakiness: a 403 from the business
 * middleware or a 422 from a FormRequest will fail identically forever, so
 * retrying only delays the error state the screen already knows how to render.
 */
function shouldRetry(failureCount: number, error: Error): boolean {
    const status = isAxiosError(error) ? error.response?.status : undefined;

    if (status !== undefined && status >= 400 && status < 500) {
        return false;
    }

    return failureCount < MAX_RETRIES;
}

/**
 * The single QueryClient, created once and provided in app.tsx so the cache
 * survives Inertia's page transitions.
 *
 * Because the backend never serialises `business_id`, query keys carry no
 * tenant discriminator. That makes `queryClient.clear()` mandatory on both
 * sign-in and sign-out: without it the previous session's rows stay in memory
 * under exactly the same keys.
 */
export const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            retry: shouldRetry,
            staleTime: 30_000,
            refetchOnWindowFocus: false,
        },
        mutations: {
            retry: false,
        },
    },
});

import { QueryClient } from '@tanstack/react-query';
import { isAxiosError } from 'axios';

const MAX_RETRIES = 2;

function shouldRetry(failureCount: number, error: Error): boolean {
    const status = isAxiosError(error) ? error.response?.status : undefined;

    if (status !== undefined && status >= 400 && status < 500) {
        return false;
    }

    return failureCount < MAX_RETRIES;
}

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

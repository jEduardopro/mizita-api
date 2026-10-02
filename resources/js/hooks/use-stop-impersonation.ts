import { router } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import { useCallback } from 'react';

const STOP_IMPERSONATION_URL = '/mizita-admin/impersonation/stop';

export function useStopImpersonation(): () => void {
    const queryClient = useQueryClient();

    return useCallback(() => {
        router.post(
            STOP_IMPERSONATION_URL,
            {},
            {
                onBefore: () => {
                    queryClient.clear();
                },
            },
        );
    }, [queryClient]);
}

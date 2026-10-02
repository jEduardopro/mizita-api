import { router } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import { useCallback } from 'react';
import { PLATFORM_LOGOUT_URL } from '@/lib/platform-urls';

export function usePlatformLogOut(): () => void {
    const queryClient = useQueryClient();

    return useCallback(() => {
        router.post(
            PLATFORM_LOGOUT_URL,
            {},
            {
                onBefore: () => {
                    queryClient.clear();
                },
            },
        );
    }, [queryClient]);
}

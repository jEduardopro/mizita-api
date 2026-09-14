import { router } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import { useCallback } from 'react';

export function useLogOut(): () => void {
    const queryClient = useQueryClient();

    return useCallback(() => {
        router.post(
            '/logout',
            {},
            {
                onBefore: () => {
                    queryClient.clear();
                },
            },
        );
    }, [queryClient]);
}

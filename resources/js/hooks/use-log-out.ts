import { router } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import { useCallback } from 'react';

/**
 * Query keys hold no tenant discriminator — the backend never serialises
 * `business_id` — so the closing session's rows sit under exactly the keys the
 * next session will read. Clearing them is the reason this is shared.
 */
export function useLogOut(): () => void {
    const queryClient = useQueryClient();

    return useCallback(() => {
        router.post(
            '/logout',
            {},
            {
                // Block body: returning a value from `onBefore` cancels the visit.
                onBefore: () => {
                    queryClient.clear();
                },
            },
        );
    }, [queryClient]);
}

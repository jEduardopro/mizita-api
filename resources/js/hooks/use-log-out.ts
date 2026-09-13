import { router } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import { useCallback } from 'react';

/**
 * Ends the session and empties the query cache.
 *
 * It is shared because of the invariant it carries, not because the markup
 * repeats: query keys hold no tenant discriminator — the backend never
 * serialises `business_id` — so the rows of the session being closed sit in
 * memory under exactly the keys the next session will read. Every screen with a
 * log-out button has to clear them, and none of them should have to remember.
 *
 * `onBefore` runs before the request, so the cache is gone the moment logging
 * out is asked for rather than when the redirect lands. Returning `false` there
 * would cancel the visit, hence the block body.
 */
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

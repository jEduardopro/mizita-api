import { router, usePage } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import { useCallback } from 'react';
import { useStopImpersonation } from '@/hooks/use-stop-impersonation';

export function useLogOut(): () => void {
    const queryClient = useQueryClient();
    const { impersonation } = usePage().props;
    const stopImpersonation = useStopImpersonation();

    const logOut = useCallback(() => {
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

    return impersonation ? stopImpersonation : logOut;
}

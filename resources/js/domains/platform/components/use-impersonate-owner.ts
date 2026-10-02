import { router } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import { useCallback, useState } from 'react';
import { impersonationUrl } from './platform-business-urls';

type ImpersonateOwner = {
    enterAsOwner: (businessId: string) => void;
    isEntering: boolean;
};

export function useImpersonateOwner(): ImpersonateOwner {
    const queryClient = useQueryClient();
    const [isEntering, setIsEntering] = useState(false);

    const enterAsOwner = useCallback(
        (businessId: string) => {
            router.post(
                impersonationUrl(businessId),
                {},
                {
                    onBefore: () => {
                        queryClient.clear();
                    },
                    onStart: () => setIsEntering(true),
                    onFinish: () => setIsEntering(false),
                },
            );
        },
        [queryClient],
    );

    return { enterAsOwner, isEntering };
}

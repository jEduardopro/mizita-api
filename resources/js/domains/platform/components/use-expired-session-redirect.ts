import { useEffect } from 'react';
import { isUnauthenticatedError } from '@/lib/http';
import { PLATFORM_LOGIN_URL } from '@/lib/platform-urls';

export function useExpiredSessionRedirect(error: unknown): void {
    useEffect(() => {
        if (! isUnauthenticatedError(error)) {
            return;
        }

        window.location.assign(PLATFORM_LOGIN_URL);
    }, [error]);
}

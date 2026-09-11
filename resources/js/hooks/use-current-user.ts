import { useQuery } from '@tanstack/react-query';
import { api } from '@/lib/api';

/**
 * The authenticated user behind the session cookie.
 *
 * `GET /api/user` answers with the account's own fields, unwrapped. This lives
 * in `hooks/` rather than in `domains/` because there is no Accounts front-end
 * domain yet and the endpoint is app-wide rather than tenant-scoped.
 */
export type CurrentUser = {
    name: string;
    email: string;
};

export const currentUserKeys = {
    all: ['current-user'] as const,
};

async function getCurrentUser(): Promise<CurrentUser> {
    const { data } = await api.get<CurrentUser>('/user');

    return data;
}

export function useCurrentUser() {
    return useQuery({
        queryKey: currentUserKeys.all,
        queryFn: getCurrentUser,
    });
}

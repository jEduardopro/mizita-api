import { useQuery } from '@tanstack/react-query';
import { api } from '@/lib/api';

// `GET /api/user` answers unwrapped, and is app-wide rather than tenant-scoped,
// which is why this lives in `hooks/` and not in a domain.
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

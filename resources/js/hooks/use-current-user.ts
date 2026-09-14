import { useQuery } from '@tanstack/react-query';
import { api } from '@/lib/api';

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

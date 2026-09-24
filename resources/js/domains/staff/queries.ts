import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useCallback, useMemo } from 'react';
import type { CheckboxListOption } from '@/components/form/CheckboxListField';
import { currentUserKeys } from '@/hooks/use-current-user';
import {
    attachMyProfilePhoto,
    getMyProfile,
    listStaffMembers,
    removeMyProfilePhoto,
    updateMyProfile,
} from './api';
import type { MyProfile } from './types';

const DIRECTORY_LIFETIME_MS = 5 * 60 * 1000;

export const staffKeys = {
    all: ['staff'] as const,
    list: () => [...staffKeys.all, 'list'] as const,
    myProfile: () => [...staffKeys.all, 'me', 'profile'] as const,
};

export function useStaffMembers() {
    return useQuery({
        queryKey: staffKeys.list(),
        queryFn: ({ signal }) => listStaffMembers(signal),
        staleTime: DIRECTORY_LIFETIME_MS,
    });
}

type StaffChoices = {
    options: CheckboxListOption[];
    isPending: boolean;
    isError: boolean;
    refetch: () => void;
};

export function useStaffChoices(): StaffChoices {
    const { data, isPending, isError, refetch } = useStaffMembers();

    const options = useMemo(
        () => (data ?? []).map((member) => ({ value: member.id, label: member.name })),
        [data],
    );

    const retry = useCallback(() => void refetch(), [refetch]);

    return { options, isPending, isError, refetch: retry };
}

export function useMyProfile() {
    return useQuery({
        queryKey: staffKeys.myProfile(),
        queryFn: ({ signal }) => getMyProfile(signal),
    });
}

function useMyProfileMutation<TVariables>(mutationFn: (variables: TVariables) => Promise<MyProfile>) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn,
        onSuccess: (profile) => {
            queryClient.setQueryData(staffKeys.myProfile(), profile);
        },
    });
}

export function useUpdateMyProfile() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: updateMyProfile,
        onSuccess: (profile) => {
            queryClient.setQueryData(staffKeys.myProfile(), profile);

            return Promise.all([
                queryClient.invalidateQueries({ queryKey: currentUserKeys.all }),
                queryClient.invalidateQueries({ queryKey: staffKeys.list() }),
            ]);
        },
    });
}

export function useAttachMyProfilePhoto() {
    return useMyProfileMutation((photo: File) => attachMyProfilePhoto(photo));
}

export function useRemoveMyProfilePhoto() {
    return useMyProfileMutation<void>(() => removeMyProfilePhoto());
}

export function useRefreshMyProfile(): () => void {
    const queryClient = useQueryClient();

    return useCallback(
        () => void queryClient.invalidateQueries({ queryKey: staffKeys.myProfile() }),
        [queryClient],
    );
}

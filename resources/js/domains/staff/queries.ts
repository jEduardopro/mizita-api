import {
    hashKey,
    keepPreviousData,
    useMutation,
    useQuery,
    useQueryClient,
    type QueryClient,
} from '@tanstack/react-query';
import { useCallback, useMemo } from 'react';
import type { CheckboxListOption } from '@/components/form/CheckboxListField';
import { currentUserKeys } from '@/hooks/use-current-user';
import { errorCodeFrom, isNotFoundError } from '@/lib/http';
import {
    attachMyProfilePhoto,
    attachTeamMemberPhoto,
    checkTeamMemberRemoval,
    getMyProfile,
    getTeamMember,
    inviteTeamMembers,
    listStaffMembers,
    listTeamMembers,
    removeMyProfilePhoto,
    removeTeamMember,
    removeTeamMemberPhoto,
    resendTeamInvitation,
    revealTemporaryPassword,
    updateMyProfile,
    updateTeamMember,
} from './api';
import {
    TEMPORARY_PASSWORD_UNAVAILABLE_CODE,
    type MyProfile,
    type TeamListParams,
    type TeamMember,
    type UpdateTeamMemberPayload,
} from './types';

const DIRECTORY_LIFETIME_MS = 5 * 60 * 1000;

export const staffKeys = {
    all: ['staff'] as const,
    list: () => [...staffKeys.all, 'list'] as const,
    myProfile: () => [...staffKeys.all, 'me', 'profile'] as const,
    teams: () => [...staffKeys.all, 'team'] as const,
    team: (params: TeamListParams) => [...staffKeys.teams(), params] as const,
    teamMember: (id: string) => [...staffKeys.all, 'team-member', id] as const,
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

function invalidateTeamMemberViews(queryClient: QueryClient, staffMemberId: string): Promise<unknown> {
    return Promise.all([
        queryClient.invalidateQueries({ queryKey: staffKeys.teams() }),
        queryClient.invalidateQueries({ queryKey: staffKeys.teamMember(staffMemberId) }),
    ]);
}

function useMyProfileMutation<TVariables>(mutationFn: (variables: TVariables) => Promise<MyProfile>) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn,
        onSuccess: (profile) => {
            queryClient.setQueryData(staffKeys.myProfile(), profile);

            return invalidateTeamMemberViews(queryClient, profile.staff_member_id);
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
                invalidateTeamMemberViews(queryClient, profile.staff_member_id),
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

export function useTeamMembers(params: TeamListParams) {
    return useQuery({
        queryKey: staffKeys.team(params),
        queryFn: ({ signal }) => listTeamMembers(params, signal),
        placeholderData: keepPreviousData,
    });
}

export function useTeamMember(id: string) {
    return useQuery({
        queryKey: staffKeys.teamMember(id),
        queryFn: ({ signal }) => getTeamMember(id, signal),
    });
}

function useTeamMutation<TVariables, TData>(mutationFn: (variables: TVariables) => Promise<TData>) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn,
        onSuccess: () => queryClient.invalidateQueries({ queryKey: staffKeys.all }),
    });
}

function useTeamMemberIdentityMutation<TVariables>(
    mutationFn: (variables: TVariables) => Promise<TeamMember>,
) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn,
        onSuccess: (member) => {
            queryClient.setQueryData(staffKeys.teamMember(member.id), member);

            return Promise.all([
                queryClient.invalidateQueries({ queryKey: staffKeys.teams() }),
                queryClient.invalidateQueries({ queryKey: staffKeys.list() }),
                queryClient.invalidateQueries({ queryKey: staffKeys.myProfile() }),
                queryClient.invalidateQueries({ queryKey: currentUserKeys.all }),
            ]);
        },
    });
}

export function useInviteTeamMembers() {
    return useTeamMutation(inviteTeamMembers);
}

export function useUpdateTeamMember() {
    return useTeamMemberIdentityMutation(
        ({ id, payload }: { id: string; payload: UpdateTeamMemberPayload }) => updateTeamMember(id, payload),
    );
}

export function useAttachTeamMemberPhoto() {
    return useTeamMemberIdentityMutation(({ id, photo }: { id: string; photo: File }) =>
        attachTeamMemberPhoto(id, photo),
    );
}

export function useRemoveTeamMemberPhoto() {
    return useTeamMemberIdentityMutation((id: string) => removeTeamMemberPhoto(id));
}

export function useResendTeamInvitation() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: resendTeamInvitation,
        onSuccess: (_result, id) => invalidateTeamMemberViews(queryClient, id),
    });
}

function isStaleTemporaryPassword(error: unknown): boolean {
    return errorCodeFrom(error) === TEMPORARY_PASSWORD_UNAVAILABLE_CODE || isNotFoundError(error);
}

export function useRevealTemporaryPassword() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: revealTemporaryPassword,
        gcTime: 0,
        onError: async (error, id) => {
            if (! isStaleTemporaryPassword(error)) {
                return;
            }

            await invalidateTeamMemberViews(queryClient, id);
        },
    });
}

export function useCheckTeamMemberRemoval() {
    return useMutation({
        mutationFn: (id: string) => checkTeamMemberRemoval(id),
        gcTime: 0,
    });
}

export function useRemoveTeamMember() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: removeTeamMember,
        onSuccess: (_result, id) => {
            const removedMemberKey = staffKeys.teamMember(id);
            const removedMemberHash = hashKey(removedMemberKey);

            queryClient.removeQueries({ queryKey: removedMemberKey, type: 'inactive' });

            return queryClient.invalidateQueries({
                queryKey: staffKeys.all,
                predicate: (query) => query.queryHash !== removedMemberHash,
            });
        },
    });
}

export function useRefreshMyProfile(): () => void {
    const queryClient = useQueryClient();

    return useCallback(
        () => void queryClient.invalidateQueries({ queryKey: staffKeys.myProfile() }),
        [queryClient],
    );
}

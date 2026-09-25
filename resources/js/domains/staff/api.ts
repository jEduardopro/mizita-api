import { api } from '@/lib/api';
import type { Paginated } from '@/types/api';
import type {
    InviteTeamMembersPayload,
    MyProfile,
    StaffMember,
    TeamListParams,
    TeamMember,
    TemporaryPassword,
    UpdateMyProfilePayload,
    UpdateTeamMemberPayload,
} from './types';

const PHOTO_FIELD = 'photo';

const TEAM_URL = '/staff-members';

function teamMemberUrl(id: string): string {
    return `${TEAM_URL}/${id}`;
}

function photoBodyFrom(photo: File): FormData {
    const body = new FormData();

    body.append(PHOTO_FIELD, photo);

    return body;
}

export async function listStaffMembers(signal?: AbortSignal): Promise<StaffMember[]> {
    const { data } = await api.get<{ data: StaffMember[] }>('/staff', { signal });

    return data.data;
}

export async function getMyProfile(signal?: AbortSignal): Promise<MyProfile> {
    const { data } = await api.get<{ data: MyProfile }>('/me/profile', { signal });

    return data.data;
}

export async function updateMyProfile(payload: UpdateMyProfilePayload): Promise<MyProfile> {
    const { data } = await api.patch<{ data: MyProfile }>('/me/profile', payload);

    return data.data;
}

export async function attachMyProfilePhoto(photo: File): Promise<MyProfile> {
    const { data } = await api.post<{ data: MyProfile }>('/me/profile/photo', photoBodyFrom(photo));

    return data.data;
}

export async function removeMyProfilePhoto(): Promise<MyProfile> {
    const { data } = await api.delete<{ data: MyProfile }>('/me/profile/photo');

    return data.data;
}

export async function listTeamMembers(
    params: TeamListParams,
    signal?: AbortSignal,
): Promise<Paginated<TeamMember>> {
    const { data } = await api.get<Paginated<TeamMember>>(TEAM_URL, { params, signal });

    return data;
}

export async function getTeamMember(id: string, signal?: AbortSignal): Promise<TeamMember> {
    const { data } = await api.get<{ data: TeamMember }>(teamMemberUrl(id), { signal });

    return data.data;
}

export async function inviteTeamMembers(payload: InviteTeamMembersPayload): Promise<TeamMember[]> {
    const { data } = await api.post<{ data: TeamMember[] }>(TEAM_URL, payload);

    return data.data;
}

export async function updateTeamMember(id: string, payload: UpdateTeamMemberPayload): Promise<TeamMember> {
    const { data } = await api.patch<{ data: TeamMember }>(teamMemberUrl(id), payload);

    return data.data;
}

export async function attachTeamMemberPhoto(id: string, photo: File): Promise<TeamMember> {
    const { data } = await api.post<{ data: TeamMember }>(
        `${teamMemberUrl(id)}/photo`,
        photoBodyFrom(photo),
    );

    return data.data;
}

export async function removeTeamMemberPhoto(id: string): Promise<TeamMember> {
    const { data } = await api.delete<{ data: TeamMember }>(`${teamMemberUrl(id)}/photo`);

    return data.data;
}

export async function resendTeamInvitation(id: string): Promise<void> {
    await api.post(`${teamMemberUrl(id)}/invitation`);
}

export async function revealTemporaryPassword(id: string): Promise<string> {
    const { data } = await api.get<{ data: TemporaryPassword }>(`${teamMemberUrl(id)}/temporary-password`);

    return data.data.temporary_password;
}

export async function removeTeamMember(id: string): Promise<void> {
    await api.delete(teamMemberUrl(id));
}

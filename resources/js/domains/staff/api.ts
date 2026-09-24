import { api } from '@/lib/api';
import type { MyProfile, StaffMember, UpdateMyProfilePayload } from './types';

const PHOTO_FIELD = 'photo';

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
    const body = new FormData();

    body.append(PHOTO_FIELD, photo);

    const { data } = await api.post<{ data: MyProfile }>('/me/profile/photo', body);

    return data.data;
}

export async function removeMyProfilePhoto(): Promise<MyProfile> {
    const { data } = await api.delete<{ data: MyProfile }>('/me/profile/photo');

    return data.data;
}

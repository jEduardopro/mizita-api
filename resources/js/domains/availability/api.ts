import { api } from '@/lib/api';
import type { MySchedule, ReplaceSchedulePayload } from './types';

const MY_SCHEDULE_URL = '/me/schedule';

function staffScheduleUrl(staffMemberId: string): string {
    return `/staff-members/${staffMemberId}/schedule`;
}

export async function getMySchedule(signal?: AbortSignal): Promise<MySchedule> {
    const { data } = await api.get<{ data: MySchedule }>(MY_SCHEDULE_URL, { signal });

    return data.data;
}

export async function replaceMySchedule(payload: ReplaceSchedulePayload): Promise<MySchedule> {
    const { data } = await api.put<{ data: MySchedule }>(MY_SCHEDULE_URL, payload);

    return data.data;
}

export async function getStaffSchedule(staffMemberId: string, signal?: AbortSignal): Promise<MySchedule> {
    const { data } = await api.get<{ data: MySchedule }>(staffScheduleUrl(staffMemberId), { signal });

    return data.data;
}

export async function replaceStaffSchedule(
    staffMemberId: string,
    payload: ReplaceSchedulePayload,
): Promise<MySchedule> {
    const { data } = await api.put<{ data: MySchedule }>(staffScheduleUrl(staffMemberId), payload);

    return data.data;
}

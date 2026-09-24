import { api } from '@/lib/api';
import type { MySchedule, ReplaceSchedulePayload } from './types';

export async function getMySchedule(signal?: AbortSignal): Promise<MySchedule> {
    const { data } = await api.get<{ data: MySchedule }>('/me/schedule', { signal });

    return data.data;
}

export async function replaceMySchedule(payload: ReplaceSchedulePayload): Promise<MySchedule> {
    const { data } = await api.put<{ data: MySchedule }>('/me/schedule', payload);

    return data.data;
}

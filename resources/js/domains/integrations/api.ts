import { api } from '@/lib/api';
import type { CalendarAuthorization, Integration } from './types';

export async function listIntegrations(signal?: AbortSignal): Promise<Integration[]> {
    const { data } = await api.get<{ data: Integration[] }>('/integrations', { signal });

    return data.data;
}

export async function createGoogleCalendarAuthorization(): Promise<CalendarAuthorization> {
    const { data } = await api.post<{ data: CalendarAuthorization }>(
        '/integrations/google-calendar/authorizations',
    );

    return data.data;
}

export async function deleteGoogleCalendarConnection(): Promise<void> {
    await api.delete('/integrations/google-calendar/connection');
}

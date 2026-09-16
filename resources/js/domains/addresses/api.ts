import { api } from '@/lib/api';
import type { State } from './types';

export async function listStates(country: string, signal?: AbortSignal): Promise<State[]> {
    const { data } = await api.get<{ data: State[] }>('/states', {
        params: { country },
        signal,
    });

    return data.data;
}

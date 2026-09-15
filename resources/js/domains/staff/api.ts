import { api } from '@/lib/api';
import type { StaffMember } from './types';

export async function listStaffMembers(signal?: AbortSignal): Promise<StaffMember[]> {
    const { data } = await api.get<{ data: StaffMember[] }>('/staff', { signal });

    return data.data;
}

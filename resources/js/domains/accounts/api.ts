import { api } from '@/lib/api';
import type { UpdatePasswordPayload } from './types';

const WEB_ROOT = '/';

export async function updatePassword(payload: UpdatePasswordPayload): Promise<void> {
    await api.put('/user/password', payload, { baseURL: WEB_ROOT });
}

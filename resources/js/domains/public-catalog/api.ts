import { api } from '@/lib/api';
import type { PublicBusinessPage } from './types';

export async function fetchPublicBusinessPage(
    slug: string,
    signal?: AbortSignal,
): Promise<PublicBusinessPage> {
    const { data } = await api.get<{ data: PublicBusinessPage }>(
        `/public/businesses/${encodeURIComponent(slug)}`,
        { signal },
    );

    return data.data;
}

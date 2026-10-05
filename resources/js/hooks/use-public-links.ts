import { usePage } from '@inertiajs/react';
import type { PublicLinks } from '@/types/inertia';

export function usePublicLinks(): PublicLinks {
    return usePage().props.publicLinks;
}

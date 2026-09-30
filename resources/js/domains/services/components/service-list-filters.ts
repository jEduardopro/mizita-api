import type { ServiceListFilters } from '../types';

export function serviceListFilters(search: string, staffIds: readonly string[]): ServiceListFilters {
    return {
        search: search === '' ? undefined : search,
        staff_ids: staffIds.length === 0 ? undefined : [...staffIds],
    };
}

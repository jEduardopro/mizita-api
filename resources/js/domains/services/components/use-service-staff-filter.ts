import { useCallback, useMemo } from 'react';
import { useUrlQueryState } from '@/hooks/use-url-query-state';

const STAFF_IDS_PARAMETER = 'staff_ids';

const STAFF_IDS_SEPARATOR = ',';

const MAXIMUM_STAFF_FILTER_SIZE = 50;

const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

type ServiceStaffFilter = {
    staffIds: string[];
    setStaffIds: (staffIds: string[]) => void;
};

function parseStaffIds(raw: string | null): string[] {
    if (raw === null) {
        return [];
    }

    const candidates = raw.split(STAFF_IDS_SEPARATOR).filter((candidate) => UUID_PATTERN.test(candidate));

    return [...new Set(candidates)].slice(0, MAXIMUM_STAFF_FILTER_SIZE);
}

export function useServiceStaffFilter(): ServiceStaffFilter {
    const { read, write } = useUrlQueryState();

    const raw = read(STAFF_IDS_PARAMETER);
    const staffIds = useMemo(() => parseStaffIds(raw), [raw]);

    const setStaffIds = useCallback(
        (next: string[]) =>
            write({
                page: null,
                [STAFF_IDS_PARAMETER]: next.length === 0 ? null : next.join(STAFF_IDS_SEPARATOR),
            }),
        [write],
    );

    return useMemo(() => ({ staffIds, setStaffIds }), [staffIds, setStaffIds]);
}

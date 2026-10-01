import { useCallback, useMemo } from 'react';
import { dateFromIso } from '@/components/form/date-format';
import { useUrlQueryState } from '@/hooks/use-url-query-state';
import type { StatisticsRange } from '../types';

const FROM_PARAMETER = 'from';

const TO_PARAMETER = 'to';

type StatisticsRangeState = {
    range: StatisticsRange | null;
    applyRange: (range: StatisticsRange) => void;
    clearRange: () => void;
};

function isCalendarDate(value: string | null): value is string {
    return value !== null && dateFromIso(value) !== null;
}

export function useStatisticsRange(): StatisticsRangeState {
    const urlQuery = useUrlQueryState();
    const from = urlQuery.read(FROM_PARAMETER);
    const to = urlQuery.read(TO_PARAMETER);

    const range = useMemo(
        () => (isCalendarDate(from) && isCalendarDate(to) ? { from, to } : null),
        [from, to],
    );

    const applyRange = useCallback(
        (next: StatisticsRange) => urlQuery.write({ [FROM_PARAMETER]: next.from, [TO_PARAMETER]: next.to }),
        [urlQuery],
    );

    const clearRange = useCallback(
        () => urlQuery.write({ [FROM_PARAMETER]: null, [TO_PARAMETER]: null }),
        [urlQuery],
    );

    return { range, applyRange, clearRange };
}

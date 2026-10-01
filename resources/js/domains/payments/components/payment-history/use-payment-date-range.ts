import { useCallback, useMemo } from 'react';
import { dateFromIso } from '@/components/form/date-format';
import type { DateRangeValue } from '@/components/form/date-range';
import { useUrlQueryState } from '@/hooks/use-url-query-state';
import {
    ALL_DATES,
    FROM_PARAMETER,
    PAGE_PARAMETER,
    RANGE_PARAMETER,
    TO_PARAMETER,
} from './payment-history-parameters';

const YEAR_MONTH_LENGTH = 'YYYY-MM'.length;

const FIRST_DAY_OF_MONTH = '01';

type PaymentDateRangeState = {
    dateRange: DateRangeValue | null;
    applyDateRange: (range: DateRangeValue) => void;
    clearDateRange: () => void;
};

function isCalendarDate(value: string | null): value is string {
    return value !== null && dateFromIso(value) !== null;
}

function monthToDate(today: string): DateRangeValue {
    return { from: `${today.slice(0, YEAR_MONTH_LENGTH)}-${FIRST_DAY_OF_MONTH}`, to: today };
}

export function usePaymentDateRange(today: string): PaymentDateRangeState {
    const { read, write } = useUrlQueryState();
    const from = read(FROM_PARAMETER);
    const to = read(TO_PARAMETER);
    const range = read(RANGE_PARAMETER);

    const dateRange = useMemo(() => {
        if (range === ALL_DATES) {
            return null;
        }

        if (isCalendarDate(from) && isCalendarDate(to) && to >= from) {
            return { from, to };
        }

        return monthToDate(today);
    }, [from, to, range, today]);

    const applyDateRange = useCallback(
        (next: DateRangeValue) =>
            write({
                [PAGE_PARAMETER]: null,
                [FROM_PARAMETER]: next.from,
                [TO_PARAMETER]: next.to,
                [RANGE_PARAMETER]: null,
            }),
        [write],
    );

    const clearDateRange = useCallback(
        () =>
            write({
                [PAGE_PARAMETER]: null,
                [FROM_PARAMETER]: null,
                [TO_PARAMETER]: null,
                [RANGE_PARAMETER]: ALL_DATES,
            }),
        [write],
    );

    return useMemo(
        () => ({ dateRange, applyDateRange, clearDateRange }),
        [dateRange, applyDateRange, clearDateRange],
    );
}

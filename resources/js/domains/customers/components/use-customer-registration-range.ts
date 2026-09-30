import { useCallback, useMemo } from 'react';
import { dateFromIso } from '@/components/form/date-format';
import { useUrlQueryState } from '@/hooks/use-url-query-state';
import type { CustomerRegistrationRange } from '../types';

const FROM_PARAMETER = 'created_from';

const TO_PARAMETER = 'created_to';

type CustomerRegistrationRangeState = {
    registrationRange: CustomerRegistrationRange | null;
    applyRegistrationRange: (range: CustomerRegistrationRange) => void;
    clearRegistrationRange: () => void;
};

function isCalendarDate(value: string | null): value is string {
    return value !== null && dateFromIso(value) !== null;
}

export function useCustomerRegistrationRange(): CustomerRegistrationRangeState {
    const { read, write } = useUrlQueryState();
    const from = read(FROM_PARAMETER);
    const to = read(TO_PARAMETER);

    const registrationRange = useMemo(
        () => (isCalendarDate(from) && isCalendarDate(to) && to >= from ? { from, to } : null),
        [from, to],
    );

    const applyRegistrationRange = useCallback(
        (range: CustomerRegistrationRange) =>
            write({ page: null, [FROM_PARAMETER]: range.from, [TO_PARAMETER]: range.to }),
        [write],
    );

    const clearRegistrationRange = useCallback(
        () => write({ page: null, [FROM_PARAMETER]: null, [TO_PARAMETER]: null }),
        [write],
    );

    return { registrationRange, applyRegistrationRange, clearRegistrationRange };
}

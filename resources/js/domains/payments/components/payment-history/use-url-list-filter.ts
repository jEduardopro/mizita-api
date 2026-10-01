import { useCallback, useMemo } from 'react';
import { useUrlQueryState } from '@/hooks/use-url-query-state';
import { PAGE_PARAMETER } from './payment-history-parameters';

const LIST_SEPARATOR = ',';

type Params<TValue extends string> = {
    parameter: string;
    accepts: (candidate: string) => candidate is TValue;
    maximumSize: number;
};

export type UrlListFilter<TValue extends string> = {
    values: TValue[];
    setValues: (values: readonly string[]) => void;
};

function acceptedValues<TValue extends string>(
    candidates: readonly string[],
    accepts: (candidate: string) => candidate is TValue,
    maximumSize: number,
): TValue[] {
    return [...new Set(candidates.filter(accepts))].slice(0, maximumSize);
}

export function useUrlListFilter<TValue extends string>({
    parameter,
    accepts,
    maximumSize,
}: Params<TValue>): UrlListFilter<TValue> {
    const { read, write } = useUrlQueryState();
    const raw = read(parameter);

    const values = useMemo(
        () => (raw === null ? [] : acceptedValues(raw.split(LIST_SEPARATOR), accepts, maximumSize)),
        [raw, accepts, maximumSize],
    );

    const setValues = useCallback(
        (next: readonly string[]) => {
            const accepted = acceptedValues(next, accepts, maximumSize);

            write({
                [PAGE_PARAMETER]: null,
                [parameter]: accepted.length === 0 ? null : accepted.join(LIST_SEPARATOR),
            });
        },
        [write, parameter, accepts, maximumSize],
    );

    return useMemo(() => ({ values, setValues }), [values, setValues]);
}

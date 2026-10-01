import { useCallback, useMemo } from 'react';
import { useUrlQueryState } from '@/hooks/use-url-query-state';
import { SALE_REFERENCE_MAX_LENGTH } from '../../types';
import { PAGE_PARAMETER, REFERENCE_PARAMETER } from './payment-history-parameters';

type ReferenceFilter = {
    reference: string | null;
    applyReference: (reference: string) => void;
    clearReference: () => void;
};

export function normalizeReference(text: string): string {
    return text.toUpperCase();
}

function readReference(raw: string | null): string | null {
    const reference = normalizeReference(raw?.trim() ?? '').slice(0, SALE_REFERENCE_MAX_LENGTH);

    return reference === '' ? null : reference;
}

export function useReferenceFilter(): ReferenceFilter {
    const { read, write } = useUrlQueryState();
    const reference = readReference(read(REFERENCE_PARAMETER));

    const applyReference = useCallback(
        (next: string) => write({ [PAGE_PARAMETER]: null, [REFERENCE_PARAMETER]: readReference(next) }),
        [write],
    );

    const clearReference = useCallback(
        () => write({ [PAGE_PARAMETER]: null, [REFERENCE_PARAMETER]: null }),
        [write],
    );

    return useMemo(
        () => ({ reference, applyReference, clearReference }),
        [reference, applyReference, clearReference],
    );
}

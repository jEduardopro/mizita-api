import { useCallback } from 'react';
import type { DateRangeValue } from '@/components/form/date-range';
import { useUrlQueryState } from '@/hooks/use-url-query-state';
import type { PaymentStatus, SaleListParams } from '../../types';
import { isSaleStatusFilter, listOrUndefined, SALE_STATUS_FILTERS } from './payment-filter-values';
import { EVERY_FILTER_CLEARED, STATUSES_PARAMETER } from './payment-history-parameters';
import { usePaymentCustomerFilter } from './use-payment-customer-filter';
import { usePaymentDateRange } from './use-payment-date-range';
import { useReferenceFilter } from './use-reference-filter';
import { useUrlListFilter } from './use-url-list-filter';

type SaleFilterCriteria = Pick<SaleListParams, 'from' | 'to' | 'customer_ids' | 'reference' | 'statuses'>;

export type SalesFilters = {
    dateRange: DateRangeValue | null;
    applyDateRange: (range: DateRangeValue) => void;
    clearDateRange: () => void;
    reference: string | null;
    applyReference: (reference: string) => void;
    clearReference: () => void;
    statuses: PaymentStatus[];
    setStatuses: (statuses: readonly string[]) => void;
    criteria: SaleFilterCriteria;
    hasActiveFilters: boolean;
    clearFilters: () => void;
};

export function useSalesFilters(today: string): SalesFilters {
    const { write } = useUrlQueryState();
    const { dateRange, applyDateRange, clearDateRange } = usePaymentDateRange(today);
    const customers = usePaymentCustomerFilter();
    const { reference, applyReference, clearReference } = useReferenceFilter();
    const statuses = useUrlListFilter({
        parameter: STATUSES_PARAMETER,
        accepts: isSaleStatusFilter,
        maximumSize: SALE_STATUS_FILTERS.length,
    });

    const clearFilters = useCallback(() => write(EVERY_FILTER_CLEARED), [write]);

    return {
        dateRange,
        applyDateRange,
        clearDateRange,
        reference,
        applyReference,
        clearReference,
        statuses: statuses.values,
        setStatuses: statuses.setValues,
        criteria: {
            from: dateRange?.from,
            to: dateRange?.to,
            customer_ids: listOrUndefined(customers.values),
            reference: reference ?? undefined,
            statuses: listOrUndefined(statuses.values),
        },
        hasActiveFilters:
            dateRange !== null ||
            customers.values.length > 0 ||
            reference !== null ||
            statuses.values.length > 0,
        clearFilters,
    };
}

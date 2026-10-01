import { useCallback } from 'react';
import type { DateRangeValue } from '@/components/form/date-range';
import { useUrlQueryState } from '@/hooks/use-url-query-state';
import type { TransactionListParams } from '../../types';
import {
    isMethodCode,
    isTransactionTypeFilter,
    listOrUndefined,
    METHOD_FILTER_MAXIMUM_SIZE,
    TRANSACTION_TYPE_FILTERS,
    type TransactionTypeFilter,
} from './payment-filter-values';
import { EVERY_FILTER_CLEARED, METHODS_PARAMETER, TYPES_PARAMETER } from './payment-history-parameters';
import { usePaymentCustomerFilter } from './use-payment-customer-filter';
import { usePaymentDateRange } from './use-payment-date-range';
import { useUrlListFilter } from './use-url-list-filter';

type TransactionFilterCriteria = Pick<TransactionListParams, 'from' | 'to' | 'customer_ids' | 'types' | 'methods'>;

export type TransactionsFilters = {
    dateRange: DateRangeValue | null;
    applyDateRange: (range: DateRangeValue) => void;
    clearDateRange: () => void;
    types: TransactionTypeFilter[];
    setTypes: (types: readonly string[]) => void;
    methods: string[];
    setMethods: (methods: readonly string[]) => void;
    criteria: TransactionFilterCriteria;
    hasActiveFilters: boolean;
    clearFilters: () => void;
};

export function useTransactionsFilters(today: string): TransactionsFilters {
    const { write } = useUrlQueryState();
    const { dateRange, applyDateRange, clearDateRange } = usePaymentDateRange(today);
    const customers = usePaymentCustomerFilter();
    const types = useUrlListFilter({
        parameter: TYPES_PARAMETER,
        accepts: isTransactionTypeFilter,
        maximumSize: TRANSACTION_TYPE_FILTERS.length,
    });
    const methods = useUrlListFilter({
        parameter: METHODS_PARAMETER,
        accepts: isMethodCode,
        maximumSize: METHOD_FILTER_MAXIMUM_SIZE,
    });

    const clearFilters = useCallback(() => write(EVERY_FILTER_CLEARED), [write]);

    return {
        dateRange,
        applyDateRange,
        clearDateRange,
        types: types.values,
        setTypes: types.setValues,
        methods: methods.values,
        setMethods: methods.setValues,
        criteria: {
            from: dateRange?.from,
            to: dateRange?.to,
            customer_ids: listOrUndefined(customers.values),
            types: listOrUndefined(types.values),
            methods: listOrUndefined(methods.values),
        },
        hasActiveFilters:
            dateRange !== null ||
            customers.values.length > 0 ||
            types.values.length > 0 ||
            methods.values.length > 0,
        clearFilters,
    };
}

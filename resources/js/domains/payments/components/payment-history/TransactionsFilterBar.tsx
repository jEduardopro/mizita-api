import { useMemo, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { FilterChipRow } from '@/components/admin/filter-chips/FilterChipRow';
import { OptionsFilterChip } from '@/components/admin/filter-chips/OptionsFilterChip';
import { comboboxOptionsStatus } from '@/components/form/ComboboxPanel';
import { usePaymentMethodCatalog } from '../../queries';
import { paymentMethodLabelKey } from '../payment-method-labels';
import { TRANSACTION_TYPE_FILTERS, TRANSACTION_TYPE_LABEL_KEYS } from './payment-filter-values';
import { PaymentDateRangeFilter } from './PaymentDateRangeFilter';
import { selectedOptionsLabel } from './selected-options-label';
import { useFilterChipMessages } from './use-filter-chip-messages';
import type { TransactionsFilters } from './use-transactions-filters';

type Props = {
    filters: TransactionsFilters;
    today: string;
    customerFilter: ReactNode;
};

export function TransactionsFilterBar({ filters, today, customerFilter }: Props) {
    const { t } = useTranslation('admin');
    const messages = useFilterChipMessages();
    const catalog = usePaymentMethodCatalog();

    const typeOptions = useMemo(
        () => TRANSACTION_TYPE_FILTERS.map((type) => ({ value: type, label: t(TRANSACTION_TYPE_LABEL_KEYS[type]) })),
        [t],
    );

    const methodOptions = useMemo(
        () => (catalog.data ?? []).map((method) => ({ value: method.code, label: method.name })),
        [catalog.data],
    );

    function methodLabelOf(code: string): string {
        const labelKey = paymentMethodLabelKey(code);

        return labelKey === null ? code : t(labelKey);
    }

    return (
        <FilterChipRow aria-label={t('payments.history.filters.transactionsLabel')}>
            <PaymentDateRangeFilter
                value={filters.dateRange}
                onApply={filters.applyDateRange}
                onClear={filters.clearDateRange}
                today={today}
            />

            {customerFilter}

            <OptionsFilterChip
                label={t('payments.history.filters.type')}
                valueLabel={selectedOptionsLabel(typeOptions, filters.types)}
                options={typeOptions}
                value={filters.types}
                onApply={filters.setTypes}
                onClear={() => filters.setTypes([])}
                messages={messages}
            />

            <OptionsFilterChip
                label={t('payments.history.filters.method')}
                valueLabel={selectedOptionsLabel(methodOptions, filters.methods, methodLabelOf)}
                options={methodOptions}
                value={filters.methods}
                onApply={filters.setMethods}
                onClear={() => filters.setMethods([])}
                messages={{ ...messages, error: t('payments.errors.methodsLoad') }}
                status={comboboxOptionsStatus(catalog.isPending, catalog.isError)}
                onRetry={() => void catalog.refetch()}
            />
        </FilterChipRow>
    );
}

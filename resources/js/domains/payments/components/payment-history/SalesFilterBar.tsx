import { useMemo, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { FilterChipRow } from '@/components/admin/filter-chips/FilterChipRow';
import { OptionsFilterChip } from '@/components/admin/filter-chips/OptionsFilterChip';
import { TextFilterChip } from '@/components/admin/filter-chips/TextFilterChip';
import { SALE_REFERENCE_MAX_LENGTH } from '../../types';
import { PAYMENT_STATUS_LABEL_KEYS, SALE_STATUS_FILTERS } from './payment-filter-values';
import { PaymentDateRangeFilter } from './PaymentDateRangeFilter';
import { selectedOptionsLabel } from './selected-options-label';
import { useFilterChipMessages } from './use-filter-chip-messages';
import { normalizeReference } from './use-reference-filter';
import type { SalesFilters } from './use-sales-filters';

type Props = {
    filters: SalesFilters;
    today: string;
    customerFilter: ReactNode;
};

export function SalesFilterBar({ filters, today, customerFilter }: Props) {
    const { t } = useTranslation('admin');
    const messages = useFilterChipMessages();

    const statusOptions = useMemo(
        () => SALE_STATUS_FILTERS.map((status) => ({ value: status, label: t(PAYMENT_STATUS_LABEL_KEYS[status]) })),
        [t],
    );

    return (
        <FilterChipRow aria-label={t('payments.history.filters.salesLabel')}>
            <PaymentDateRangeFilter
                value={filters.dateRange}
                onApply={filters.applyDateRange}
                onClear={filters.clearDateRange}
                today={today}
            />

            {customerFilter}

            <TextFilterChip
                label={t('payments.history.filters.reference')}
                value={filters.reference}
                onApply={filters.applyReference}
                onClear={filters.clearReference}
                placeholder={t('payments.history.filters.referencePlaceholder')}
                messages={messages}
                maxLength={SALE_REFERENCE_MAX_LENGTH}
                normalize={normalizeReference}
            />

            <OptionsFilterChip
                label={t('payments.history.filters.status')}
                valueLabel={selectedOptionsLabel(statusOptions, filters.statuses)}
                options={statusOptions}
                value={filters.statuses}
                onApply={filters.setStatuses}
                onClear={() => filters.setStatuses([])}
                messages={messages}
            />
        </FilterChipRow>
    );
}

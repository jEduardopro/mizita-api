import { useTranslation } from 'react-i18next';
import {
    OptionsFilterChip,
    type OptionsFilterOption,
} from '@/components/admin/filter-chips/OptionsFilterChip';
import { useFilterChipMessages } from '@/components/admin/filter-chips/use-filter-chip-messages';
import type { ComboboxOptionsStatus } from '@/components/form/ComboboxPanel';

type Props = {
    options: readonly OptionsFilterOption[];
    status: ComboboxOptionsStatus;
    onRetry: () => void;
    onSearchChange: (term: string) => void;
    value: readonly string[];
    firstSelectedName: string | null;
    onApply: (customerIds: string[]) => void;
    onClear: () => void;
};

export function PaymentCustomerFilter({
    options,
    status,
    onRetry,
    onSearchChange,
    value,
    firstSelectedName,
    onApply,
    onClear,
}: Props) {
    const { t } = useTranslation('admin');
    const messages = useFilterChipMessages();

    function selectionLabel(): string | null {
        if (value.length === 0) {
            return null;
        }

        if (firstSelectedName === null) {
            return t('payments.history.filters.customerCount', { count: value.length });
        }

        return value.length === 1 ? firstSelectedName : `${firstSelectedName} +${value.length - 1}`;
    }

    return (
        <OptionsFilterChip
            label={t('payments.history.filters.customer')}
            valueLabel={selectionLabel()}
            options={options}
            value={value}
            onApply={onApply}
            onClear={onClear}
            messages={{
                ...messages,
                empty: t('payments.history.filters.noCustomers'),
                error: t('payments.history.filters.customersError'),
            }}
            searchable
            onSearchChange={onSearchChange}
            status={status}
            onRetry={onRetry}
        />
    );
}

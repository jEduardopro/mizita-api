import { useTranslation } from 'react-i18next';
import {
    OptionsFilterChip,
    type OptionsFilterOption,
} from '@/components/admin/filter-chips/OptionsFilterChip';
import { selectedOptionsLabel } from '@/components/admin/filter-chips/selected-options-label';
import { useFilterChipMessages } from '@/components/admin/filter-chips/use-filter-chip-messages';
import type { ComboboxOptionsStatus } from '@/components/form/ComboboxPanel';

type Props = {
    options: readonly OptionsFilterOption[];
    status: ComboboxOptionsStatus;
    onRetry: () => void;
    value: readonly string[];
    onChange: (staffIds: string[]) => void;
};

function isListed(options: readonly OptionsFilterOption[], staffId: string): boolean {
    return options.some((option) => option.value === staffId);
}

export function ServiceStaffFilter({ options, status, onRetry, value, onChange }: Props) {
    const { t } = useTranslation('admin');
    const messages = useFilterChipMessages();

    function selectionLabel(): string | null {
        if (value.every((staffId) => isListed(options, staffId))) {
            return selectedOptionsLabel(options, value);
        }

        return t('services.filters.staff.selected', { count: value.length });
    }

    return (
        <OptionsFilterChip
            label={t('services.filters.staff.label')}
            valueLabel={selectionLabel()}
            options={options}
            value={value}
            onApply={onChange}
            onClear={() => onChange([])}
            messages={{ ...messages, empty: t('services.filters.staff.empty') }}
            searchable
            status={status}
            onRetry={onRetry}
        />
    );
}

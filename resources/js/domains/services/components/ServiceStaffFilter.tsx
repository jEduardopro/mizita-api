import { Users } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { ComboboxOptionsStatus } from '@/components/form/ComboboxPanel';
import {
    MultiCombobox,
    type MultiComboboxOption,
} from '@/components/shared/multi-combobox/MultiCombobox';

type Props = {
    options: readonly MultiComboboxOption[];
    status: ComboboxOptionsStatus;
    onRetry: () => void;
    value: readonly string[];
    onChange: (staffIds: string[]) => void;
};

export function ServiceStaffFilter({ options, status, onRetry, value, onChange }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');

    return (
        <MultiCombobox
            label={t('services.filters.staff.label')}
            options={options}
            value={value}
            onChange={onChange}
            status={status}
            onRetryOptions={onRetry}
            icon={<Users aria-hidden="true" className="text-muted-foreground" />}
            messages={{
                placeholder: t('services.filters.staff.placeholder'),
                search: t('services.filters.staff.search'),
                empty: t('services.filters.staff.empty'),
                clear: t('services.filters.staff.clear'),
                selected: t('services.filters.staff.selected', { count: value.length }),
                optionsError: tCommon('table.error.title'),
                retry: tCommon('actions.tryAgain'),
            }}
            className="sm:w-60 sm:shrink-0"
        />
    );
}

import { useTranslation } from 'react-i18next';
import { DateRangePicker } from '@/components/form/DateRangePicker';
import type { CustomerRegistrationRange } from '../types';

type Props = {
    value: CustomerRegistrationRange | null;
    onApply: (range: CustomerRegistrationRange) => void;
    onClear: () => void;
    today?: string;
};

export function CustomerRegistrationRangeFilter({ value, onApply, onClear, today }: Props) {
    const { t } = useTranslation('admin');

    return (
        <DateRangePicker
            label={t('customers.filters.registered.label')}
            labelDisplay="hidden"
            placeholder={t('customers.filters.registered.placeholder')}
            value={value}
            onApply={onApply}
            onClear={onClear}
            today={today}
            className="sm:w-72"
            fieldClassName="md:h-9"
        />
    );
}

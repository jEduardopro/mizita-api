import { useTranslation } from 'react-i18next';
import { DateRangeFilterChip } from '@/components/admin/filter-chips/DateRangeFilterChip';
import { useDateRangeChipMessages } from '@/components/admin/filter-chips/use-date-range-chip-messages';
import type { DateRangeValue } from '@/components/form/date-range';

type Props = {
    value: DateRangeValue | null;
    onApply: (range: DateRangeValue) => void;
    onClear: () => void;
    today: string;
};

export function PaymentDateRangeFilter({ value, onApply, onClear, today }: Props) {
    const { t } = useTranslation('common');
    const messages = useDateRangeChipMessages();

    return (
        <DateRangeFilterChip
            label={t('dateRange.label')}
            value={value}
            onApply={onApply}
            onClear={onClear}
            today={today}
            messages={messages}
        />
    );
}

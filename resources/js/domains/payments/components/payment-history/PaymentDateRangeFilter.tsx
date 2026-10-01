import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import {
    DateRangeFilterChip,
    type DateRangeFilterChipMessages,
} from '@/components/admin/filter-chips/DateRangeFilterChip';
import type { DateRangeValue } from '@/components/form/date-range';

type Props = {
    value: DateRangeValue | null;
    onApply: (range: DateRangeValue) => void;
    onClear: () => void;
    today: string;
};

export function PaymentDateRangeFilter({ value, onApply, onClear, today }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');

    const messages = useMemo<DateRangeFilterChipMessages>(
        () => ({
            apply: tCommon('dateRange.apply'),
            clear: tCommon('dateRange.clear'),
            presetsLabel: tCommon('dateRange.presetsLabel'),
            presets: tCommon('dateRange.presets', { returnObjects: true }),
            previousMonth: tCommon('dateRange.previousMonth'),
            nextMonth: tCommon('dateRange.nextMonth'),
            today: tCommon('dateRange.today'),
        }),
        [tCommon],
    );

    return (
        <DateRangeFilterChip
            label={t('payments.history.filters.date')}
            value={value}
            onApply={onApply}
            onClear={onClear}
            today={today}
            max={today}
            messages={messages}
        />
    );
}

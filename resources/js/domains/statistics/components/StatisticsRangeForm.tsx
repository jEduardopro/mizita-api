import { useTranslation } from 'react-i18next';
import { DateRangeFilterChip } from '@/components/admin/filter-chips/DateRangeFilterChip';
import { FilterChipRow } from '@/components/admin/filter-chips/FilterChipRow';
import { useDateRangeChipMessages } from '@/components/admin/filter-chips/use-date-range-chip-messages';
import type { StatisticsRange } from '../types';

type Props = {
    value: StatisticsRange | null;
    latest: string;
    onApply: (range: StatisticsRange) => void;
    onClear: () => void;
};

export function StatisticsRangeForm({ value, latest, onApply, onClear }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');
    const messages = useDateRangeChipMessages();

    return (
        <FilterChipRow aria-label={t('statistics.range.filtersLabel')}>
            <DateRangeFilterChip
                label={tCommon('dateRange.label')}
                value={value}
                onApply={onApply}
                onClear={onClear}
                today={latest}
                max={latest}
                messages={messages}
            />
        </FilterChipRow>
    );
}

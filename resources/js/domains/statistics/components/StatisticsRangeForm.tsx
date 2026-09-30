import { useTranslation } from 'react-i18next';
import { DateRangePicker } from '@/components/form/DateRangePicker';
import type { StatisticsRange } from '../types';

type Props = {
    value: StatisticsRange | null;
    latest: string;
    onApply: (range: StatisticsRange) => void;
};

export function StatisticsRangeForm({ value, latest, onApply }: Props) {
    const { t } = useTranslation('admin');

    return (
        <DateRangePicker
            label={t('statistics.range.label')}
            placeholder={t('statistics.range.placeholder')}
            value={value}
            onApply={onApply}
            today={latest}
            max={latest}
            className="sm:w-80"
        />
    );
}

import { useTranslation } from 'react-i18next';
import { StatisticsContent } from '@/domains/statistics/components/StatisticsContent';
import { StatisticsRangeForm } from '@/domains/statistics/components/StatisticsRangeForm';
import { useStatisticsRange } from '@/domains/statistics/components/use-statistics-range';
import { useStatistics } from '@/domains/statistics/queries';
import type { BusinessStatistics, StatisticsRange } from '@/domains/statistics/types';
import { AdminLayout } from '@/layouts/AdminLayout';
import { todayAsIsoDate } from '@/lib/time';

function periodOf(statistics: BusinessStatistics | undefined): StatisticsRange | null {
    return statistics === undefined ? null : { from: statistics.period.from, to: statistics.period.to };
}

export default function Statistics() {
    const { t } = useTranslation('admin');
    const { range, applyRange, clearRange } = useStatisticsRange();
    const { data: statistics, error, isPlaceholderData, refetch } = useStatistics(range);

    return (
        <AdminLayout title={t('statistics.title')}>
            <div className="grid gap-6">
                <StatisticsRangeForm
                    value={range ?? periodOf(statistics)}
                    latest={statistics?.today.date ?? todayAsIsoDate()}
                    onApply={applyRange}
                    onClear={clearRange}
                />

                <StatisticsContent
                    statistics={statistics}
                    error={error}
                    refreshing={isPlaceholderData}
                    onRetry={() => void refetch()}
                />
            </div>
        </AdminLayout>
    );
}

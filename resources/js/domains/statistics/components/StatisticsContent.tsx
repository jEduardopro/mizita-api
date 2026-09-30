import type { BusinessStatistics } from '../types';
import { StatisticsDashboard } from './StatisticsDashboard';
import { StatisticsLoadError } from './StatisticsLoadError';
import { StatisticsSkeleton } from './StatisticsSkeleton';

type Props = {
    statistics: BusinessStatistics | undefined;
    error: unknown;
    refreshing: boolean;
    onRetry: () => void;
};

export function StatisticsContent({ statistics, error, refreshing, onRetry }: Props) {
    if (error !== null) {
        return <StatisticsLoadError error={error} onRetry={onRetry} />;
    }

    if (statistics === undefined) {
        return <StatisticsSkeleton />;
    }

    return <StatisticsDashboard statistics={statistics} refreshing={refreshing} />;
}

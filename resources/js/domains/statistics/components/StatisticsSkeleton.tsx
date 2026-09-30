import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import { Skeleton } from '@/components/ui/skeleton';
import {
    COLLECTED_PLACEMENT,
    COMPARISON_PLACEMENT,
    LAST_SEVEN_DAYS_PLACEMENT,
    STATISTICS_ROW_CLASS,
    TODAY_PLACEMENT,
} from './statistics-layout';

const CARD_CLASS = 'rounded-xl';

const BREAKDOWN_CARD_COUNT = 4;

const BREAKDOWN_CARDS = Array.from({ length: BREAKDOWN_CARD_COUNT }, (_, position) => `breakdown-${position}`);

export function StatisticsSkeleton() {
    const { t } = useTranslation('admin');

    return (
        <div role="status" aria-label={t('statistics.loading')} className="grid gap-4">
            <div className={STATISTICS_ROW_CLASS}>
                <Skeleton className={cn(CARD_CLASS, 'h-36', COLLECTED_PLACEMENT)} />
                <Skeleton className={cn(CARD_CLASS, 'h-60 sm:h-auto', COMPARISON_PLACEMENT)} />
                <Skeleton className={cn(CARD_CLASS, 'h-80', LAST_SEVEN_DAYS_PLACEMENT)} />
                <Skeleton className={cn(CARD_CLASS, 'h-36', TODAY_PLACEMENT)} />
            </div>

            <div className={STATISTICS_ROW_CLASS}>
                {BREAKDOWN_CARDS.map((card) => (
                    <Skeleton key={card} className={cn(CARD_CLASS, 'h-72')} />
                ))}
            </div>
        </div>
    );
}

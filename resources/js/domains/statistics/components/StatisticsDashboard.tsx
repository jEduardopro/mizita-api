import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import type { BusinessStatistics } from '../types';
import { AppointmentsSummaryCard } from './AppointmentsSummaryCard';
import { CustomersSummaryCard } from './CustomersSummaryCard';
import { LastSevenDaysCard } from './LastSevenDaysCard';
import { MoneyHighlightCard } from './MoneyHighlightCard';
import { PaymentMethodBreakdownCard } from './PaymentMethodBreakdownCard';
import { PeriodComparisonCard } from './PeriodComparisonCard';
import { StaffBreakdownCard } from './StaffBreakdownCard';
import { formatDateRange, formatLongDate } from './statistics-format';
import {
    COLLECTED_PLACEMENT,
    COMPARISON_PLACEMENT,
    LAST_SEVEN_DAYS_PLACEMENT,
    STATISTICS_ROW_CLASS,
    TODAY_PLACEMENT,
} from './statistics-layout';

type Props = {
    statistics: BusinessStatistics;
    refreshing: boolean;
};

export function StatisticsDashboard({ statistics, refreshing }: Props) {
    const { t, i18n } = useTranslation('admin');
    const locale = i18n.language;

    return (
        <div
            aria-busy={refreshing}
            className={cn(
                'grid gap-4 transition-opacity motion-reduce:transition-none',
                refreshing && 'opacity-60',
            )}
        >
            <section aria-label={t('statistics.sections.sales')} className={STATISTICS_ROW_CLASS}>
                <MoneyHighlightCard
                    title={t('statistics.collected.title')}
                    caption={formatDateRange(statistics.period.from, statistics.period.to, locale)}
                    cents={statistics.period.collected_cents}
                    className={COLLECTED_PLACEMENT}
                />

                <PeriodComparisonCard
                    current={statistics.period}
                    previous={statistics.previous_period}
                    changePercent={statistics.change_percent}
                    className={COMPARISON_PLACEMENT}
                />

                <LastSevenDaysCard
                    days={statistics.last_7_days}
                    today={statistics.today.date}
                    className={LAST_SEVEN_DAYS_PLACEMENT}
                />

                <MoneyHighlightCard
                    title={t('statistics.today.title')}
                    caption={formatLongDate(statistics.today.date, locale)}
                    cents={statistics.today.collected_cents}
                    className={TODAY_PLACEMENT}
                />
            </section>

            <section aria-label={t('statistics.sections.breakdown')} className={STATISTICS_ROW_CLASS}>
                <PaymentMethodBreakdownCard methods={statistics.by_payment_method} />
                <StaffBreakdownCard staffMembers={statistics.by_staff} />
                <AppointmentsSummaryCard appointments={statistics.appointments} />
                <CustomersSummaryCard customers={statistics.customers} />
            </section>
        </div>
    );
}

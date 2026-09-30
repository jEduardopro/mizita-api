import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import { Card, CardAction, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatFixedMoneyFromCents } from '@/lib/money';
import type { CollectedPeriod } from '../types';
import { ChangeBadge } from './ChangeBadge';
import { HeadlineFigure } from './HeadlineFigure';
import { formatDateRange } from './statistics-format';

type Props = {
    current: CollectedPeriod;
    previous: CollectedPeriod;
    changePercent: number | null;
    className?: string;
};

export function PeriodComparisonCard({ current, previous, changePercent, className }: Props) {
    const { t, i18n } = useTranslation('admin');
    const locale = i18n.language;

    return (
        <Card className={cn('justify-between', className)}>
            <CardHeader>
                <CardTitle>{t('statistics.comparison.title')}</CardTitle>

                <CardAction>
                    <ChangeBadge changePercent={changePercent} />
                </CardAction>
            </CardHeader>

            <CardContent className="@container grid gap-5">
                <HeadlineFigure value={formatFixedMoneyFromCents(current.collected_cents)} />

                <dl className="grid gap-3 border-t pt-4">
                    <div className="grid gap-0.5">
                        <dt className="text-xs text-muted-foreground">{t('statistics.comparison.current')}</dt>
                        <dd className="font-medium tracking-wide uppercase tabular-nums">
                            {formatDateRange(current.from, current.to, locale)}
                        </dd>
                    </div>

                    <div className="grid gap-0.5">
                        <dt className="text-xs text-muted-foreground">{t('statistics.comparison.previous')}</dt>
                        <dd className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5">
                            <span className="font-medium tracking-wide uppercase tabular-nums">
                                {formatDateRange(previous.from, previous.to, locale)}
                            </span>

                            <span className="text-muted-foreground tabular-nums">
                                {formatFixedMoneyFromCents(previous.collected_cents)}
                            </span>
                        </dd>
                    </div>
                </dl>
            </CardContent>
        </Card>
    );
}

import { useTranslation } from 'react-i18next';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { CustomerStatistics } from '../types';
import { HeadlineFigure } from './HeadlineFigure';
import { MetricRow } from './MetricRow';
import { formatCount } from './statistics-format';

type Props = {
    customers: CustomerStatistics;
};

export function CustomersSummaryCard({ customers }: Props) {
    const { t, i18n } = useTranslation('admin');
    const count = (value: number) => formatCount(value, i18n.language);

    return (
        <Card>
            <CardHeader>
                <CardTitle>{t('statistics.customers.title')}</CardTitle>
            </CardHeader>

            <CardContent className="@container grid gap-4">
                <div className="grid gap-1">
                    <HeadlineFigure value={count(customers.attended)} />
                    <p className="text-muted-foreground">{t('statistics.customers.attended')}</p>
                </div>

                <dl className="divide-y border-t">
                    <MetricRow label={t('statistics.customers.new')} value={count(customers.new)} />
                    <MetricRow label={t('statistics.customers.returning')} value={count(customers.returning)} />
                </dl>
            </CardContent>
        </Card>
    );
}

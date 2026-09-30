import { useTranslation } from 'react-i18next';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { AppointmentStatistics } from '../types';
import { HeadlineFigure } from './HeadlineFigure';
import { MetricRow } from './MetricRow';
import { formatCount } from './statistics-format';

type Props = {
    appointments: AppointmentStatistics;
};

export function AppointmentsSummaryCard({ appointments }: Props) {
    const { t, i18n } = useTranslation('admin');
    const count = (value: number) => formatCount(value, i18n.language);

    return (
        <Card>
            <CardHeader>
                <CardTitle>{t('statistics.appointments.title')}</CardTitle>
            </CardHeader>

            <CardContent className="@container grid gap-4">
                <div className="grid gap-1">
                    <HeadlineFigure value={count(appointments.total)} />
                    <p className="text-muted-foreground">{t('statistics.appointments.total')}</p>
                </div>

                <dl className="divide-y border-t">
                    <MetricRow label={t('statistics.appointments.attended')} value={count(appointments.attended)} />
                    <MetricRow label={t('statistics.appointments.cancelled')} value={count(appointments.cancelled)} />
                    <MetricRow label={t('statistics.appointments.upcoming')} value={count(appointments.upcoming)} />
                </dl>

                <div className="grid gap-1">
                    <p className="text-xs font-medium text-muted-foreground">{t('statistics.appointments.bySource')}</p>

                    <dl className="divide-y">
                        <MetricRow
                            label={t('statistics.appointments.sources.admin')}
                            value={count(appointments.by_source.admin)}
                        />
                        <MetricRow
                            label={t('statistics.appointments.sources.public')}
                            value={count(appointments.by_source.public)}
                        />
                    </dl>
                </div>
            </CardContent>
        </Card>
    );
}

import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { DailyCollection } from '../types';
import { SalesTrendChart } from './SalesTrendChart';

type Props = {
    days: DailyCollection[];
    today: string;
    className?: string;
};

export function LastSevenDaysCard({ days, today, className }: Props) {
    const { t } = useTranslation('admin');

    return (
        <Card className={cn('justify-between', className)}>
            <CardHeader>
                <CardTitle>{t('statistics.lastSevenDays.title')}</CardTitle>
                <CardDescription>{t('statistics.lastSevenDays.description')}</CardDescription>
            </CardHeader>

            <CardContent className="px-1 sm:px-(--card-spacing)">
                <SalesTrendChart days={days} today={today} />
            </CardContent>
        </Card>
    );
}

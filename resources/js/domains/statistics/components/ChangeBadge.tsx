import { Minus, TrendingDown, TrendingUp, type LucideIcon } from 'lucide-react';
import type { ComponentProps } from 'react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { formatChangePercent } from './statistics-format';

type Props = {
    changePercent: number | null;
};

type Trend = {
    icon: LucideIcon;
    variant: ComponentProps<typeof Badge>['variant'];
    className?: string;
};

const RISING: Trend = { icon: TrendingUp, variant: 'secondary', className: 'bg-success/10 text-success' };

const FALLING: Trend = { icon: TrendingDown, variant: 'destructive' };

const FLAT: Trend = { icon: Minus, variant: 'secondary' };

function trendOf(changePercent: number): Trend {
    if (changePercent > 0) {
        return RISING;
    }

    return changePercent < 0 ? FALLING : FLAT;
}

export function ChangeBadge({ changePercent }: Props) {
    const { t, i18n } = useTranslation('admin');

    if (changePercent === null) {
        return null;
    }

    const trend = trendOf(changePercent);
    const Icon = trend.icon;

    return (
        <Badge variant={trend.variant} className={trend.className}>
            <Icon aria-hidden="true" data-icon="inline-start" />
            {formatChangePercent(changePercent, i18n.language)}{' '}
            <span className="sr-only">{t('statistics.comparison.versusPrevious')}</span>
        </Badge>
    );
}

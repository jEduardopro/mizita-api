import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import { type BrandColorClasses } from '@/lib/booking-brand';
import { formatTimeOfDay } from '@/lib/time';

type Props = {
    closesAt: string;
    accent: BrandColorClasses;
    className?: string;
};

export function BookingOpenBadge({ closesAt, accent, className }: Props) {
    const { t } = useTranslation('public');

    return (
        <p
            className={cn(
                'inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-medium text-pretty',
                accent.surface,
                className,
            )}
        >
            <span
                aria-hidden="true"
                className={cn('size-1.5 shrink-0 rounded-full', accent.accent)}
            />

            {t('booking.hours.openNow', { time: formatTimeOfDay(closesAt) })}
        </p>
    );
}

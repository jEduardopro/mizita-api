import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import { type BrandColorClasses } from '@/lib/booking-brand';
import { formatTimeOfDay } from '@/lib/time';
import { WEEKDAY_LABEL_KEYS } from '@/lib/weekdays';
import type { BookingOpenState } from './booking-schedule';

type Props = {
    state: BookingOpenState;
    accent: BrandColorClasses;
    className?: string;
};

export function BookingOpenBadge({ state, accent, className }: Props) {
    const { t, i18n } = useTranslation('public');
    const { t: tCommon } = useTranslation('common');

    if (state.status === 'unknown') {
        return null;
    }

    const isOpen = state.status === 'open';

    const label = isOpen
        ? t('booking.hours.openNow', { time: formatTimeOfDay(state.closesAt, i18n.language) })
        : t('booking.hours.closedNow', {
              day: tCommon(WEEKDAY_LABEL_KEYS[state.opensWeekday]),
              time: formatTimeOfDay(state.opensAt, i18n.language),
          });

    return (
        <p
            className={cn(
                'inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-medium text-pretty',
                isOpen ? accent.surface : 'bg-muted text-muted-foreground',
                className,
            )}
        >
            <span
                aria-hidden="true"
                className={cn(
                    'size-1.5 shrink-0 rounded-full',
                    isOpen ? accent.accent : 'bg-muted-foreground',
                )}
            />

            {label}
        </p>
    );
}

import { CalendarX2 } from 'lucide-react';
import 'temporal-polyfill/global';
import { useTranslation } from 'react-i18next';
import { formatTimeOfDay } from '@/lib/time';
import { CANCELLED_BY_KEYS } from './appointment-status';
import type { AppointmentCanceller } from '../types';

type Props = {
    cancelledAt: string | null;
    cancelledBy: AppointmentCanceller | null;
    timezone: string;
};

function momentLabel(instant: string, timezone: string, locale: string): string {
    const moment = Temporal.Instant.from(instant).toZonedDateTimeISO(timezone);

    const day = new Intl.DateTimeFormat(locale, {
        day: 'numeric',
        month: 'short',
        timeZone: timezone,
    }).format(new Date(moment.epochMilliseconds));

    return `${day}, ${formatTimeOfDay(moment.toPlainTime().toString({ smallestUnit: 'minute' }))}`;
}

export function CancelledAppointmentNotice({ cancelledAt, cancelledBy, timezone }: Props) {
    const { t, i18n } = useTranslation('admin');

    const details = [
        cancelledBy === null ? null : t(CANCELLED_BY_KEYS[cancelledBy]),
        cancelledAt === null ? null : momentLabel(cancelledAt, timezone, i18n.language),
    ].filter((detail): detail is string => detail !== null);

    return (
        <div className="flex items-start gap-3 rounded-lg border border-destructive/30 bg-destructive/5 p-3">
            <CalendarX2 className="mt-0.5 size-4 shrink-0 text-destructive" aria-hidden="true" />

            <div className="grid min-w-0 gap-0.5">
                <p className="text-sm font-medium text-destructive">
                    {t('calendar.appointment.cancelled.title')}
                </p>

                {details.length > 0 ? (
                    <p className="text-pretty text-sm text-muted-foreground">{details.join(' · ')}</p>
                ) : null}
            </div>
        </div>
    );
}

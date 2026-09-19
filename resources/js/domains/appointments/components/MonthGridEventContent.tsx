import type { CalendarEvent } from '@schedule-x/calendar';
import { cn } from 'cn';
import 'temporal-polyfill/global';
import { useTranslation } from 'react-i18next';
import { formatTimeOfDay } from '@/lib/time';
import { serviceColorClasses } from '@/lib/service-color';
import { isAppointmentCalendarEvent } from './appointment-events';
import { isCancelled } from './appointment-status';

type Props = {
    calendarEvent: CalendarEvent;
};

export function MonthGridEventContent({ calendarEvent }: Props) {
    const { t } = useTranslation('admin');

    if (! isAppointmentCalendarEvent(calendarEvent) || ! (calendarEvent.start instanceof Temporal.ZonedDateTime)) {
        return null;
    }

    const { appointment, start } = calendarEvent;
    const cancelled = isCancelled(appointment);
    const time = formatTimeOfDay(start.toPlainTime().toString({ smallestUnit: 'minute' }));

    return (
        <div className="flex min-w-0 items-center gap-1.5 px-1 py-0.5 text-xs">
            <span
                className={cn(
                    'size-1.5 shrink-0 rounded-full',
                    cancelled ? 'bg-muted-foreground' : serviceColorClasses[appointment.service.color].bar,
                )}
                aria-hidden="true"
            />

            {cancelled ? <span className="sr-only">{t('calendar.appointment.cancelled.badge')}</span> : null}

            <span className={cn('shrink-0 tabular-nums text-muted-foreground', cancelled && 'line-through')}>
                {time}
            </span>

            <span
                className={cn(
                    'truncate font-medium',
                    cancelled ? 'text-muted-foreground line-through' : 'text-foreground',
                )}
            >
                {appointment.customer.name}
            </span>
        </div>
    );
}

import type { CalendarEvent } from '@schedule-x/calendar';
import { cn } from 'cn';
import 'temporal-polyfill/global';
import { serviceColorClasses } from '@/lib/service-color';
import { isAppointmentCalendarEvent } from './appointment-events';

type Props = {
    calendarEvent: CalendarEvent;
    locale: string;
};

export function MonthGridEventContent({ calendarEvent, locale }: Props) {
    if (! isAppointmentCalendarEvent(calendarEvent) || ! (calendarEvent.start instanceof Temporal.ZonedDateTime)) {
        return null;
    }

    const { appointment, start } = calendarEvent;

    const time = new Intl.DateTimeFormat(locale, {
        hour: 'numeric',
        minute: '2-digit',
        timeZone: start.timeZoneId,
    }).format(new Date(start.epochMilliseconds));

    return (
        <div className="flex min-w-0 items-center gap-1.5 px-1 py-0.5 text-xs">
            <span
                className={cn('size-1.5 shrink-0 rounded-full', serviceColorClasses[appointment.service.color].bar)}
                aria-hidden="true"
            />

            <span className="shrink-0 tabular-nums text-muted-foreground">{time}</span>

            <span className="truncate font-medium text-foreground">{appointment.customer.name}</span>
        </div>
    );
}

import type { CalendarEvent } from '@schedule-x/calendar';
import { cn } from 'cn';
import 'temporal-polyfill/global';
import { useTranslation } from 'react-i18next';
import { formatTimeOfDay } from '@/lib/time';
import { appointmentPalette, isAppointmentCalendarEvent } from './appointment-events';
import { isCancelled } from './appointment-status';

type Props = {
    calendarEvent: CalendarEvent;
};

function clockTime(moment: Temporal.ZonedDateTime): string {
    return formatTimeOfDay(moment.toPlainTime().toString({ smallestUnit: 'minute' }));
}

export function TimeGridEventContent({ calendarEvent }: Props) {
    const { t } = useTranslation('admin');

    if (! isAppointmentCalendarEvent(calendarEvent)) {
        return null;
    }

    const { appointment, start, end } = calendarEvent;

    if (! (start instanceof Temporal.ZonedDateTime) || ! (end instanceof Temporal.ZonedDateTime)) {
        return null;
    }

    const cancelled = isCancelled(appointment);
    const palette = appointmentPalette(appointment);

    return (
        <div
            className="h-full overflow-hidden border-s-4 [container-type:size]"
            style={{
                backgroundColor: palette.container,
                borderInlineStartColor: palette.main,
                color: palette.onContainer,
            }}
        >
            <div className="flex h-full min-w-0 flex-col gap-x-1.5 gap-y-px px-1.5 py-1 [@container_(max-height:40px)]:flex-row [@container_(max-height:40px)]:items-baseline">
                <span className="flex min-w-0 items-baseline gap-1">
                    <span className="truncate font-semibold">{appointment.customer.name}</span>

                    {cancelled ? (
                        <span className="shrink-0 text-[0.85em] font-semibold uppercase tracking-wide [@container_(max-height:40px)]:sr-only">
                            {t('calendar.appointment.cancelled.badge')}
                        </span>
                    ) : null}
                </span>

                <span className={cn('shrink-0 truncate tabular-nums', cancelled && 'line-through')}>
                    {clockTime(start)}
                    {' – '}
                    {clockTime(end)}
                </span>
            </div>
        </div>
    );
}

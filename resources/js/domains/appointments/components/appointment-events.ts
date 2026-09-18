import type { CalendarEvent, CalendarType } from '@schedule-x/calendar';
import 'temporal-polyfill/global';
import { SERVICE_COLORS, type ServiceColor } from '@/lib/service-color';
import type { Appointment } from '../types';

export type AppointmentCalendarEvent = CalendarEvent & { appointment: Appointment };

export function appointmentsToEvents(
    appointments: Appointment[],
    timezone: string,
): AppointmentCalendarEvent[] {
    return appointments.map((appointment) => ({
        id: appointment.id,
        calendarId: appointment.service.color,
        start: Temporal.Instant.from(appointment.starts_at).toZonedDateTimeISO(timezone),
        end: Temporal.Instant.from(appointment.ends_at).toZonedDateTimeISO(timezone),
        title: appointment.customer.name,
        description: appointment.service.name,
        appointment,
    }));
}

export function isAppointmentCalendarEvent(event: CalendarEvent): event is AppointmentCalendarEvent {
    return 'appointment' in event;
}

function serviceColorCalendar(color: ServiceColor): CalendarType {
    return {
        colorName: color,
        lightColors: {
            main: `var(--color-service-${color})`,
            container: `var(--color-service-${color}-surface)`,
            onContainer: `var(--color-service-${color})`,
        },
        darkColors: {
            main: `var(--color-service-${color})`,
            container: `var(--color-service-${color}-surface)`,
            onContainer: `var(--color-service-${color})`,
        },
    };
}

export const SERVICE_COLOR_CALENDARS: Record<ServiceColor, CalendarType> = Object.fromEntries(
    SERVICE_COLORS.map((color) => [color, serviceColorCalendar(color)]),
) as Record<ServiceColor, CalendarType>;

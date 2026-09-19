import type { CalendarEvent, CalendarType } from '@schedule-x/calendar';
import 'temporal-polyfill/global';
import { SERVICE_COLORS, type ServiceColor } from '@/lib/service-color';
import { isCancelled } from './appointment-status';
import type { Appointment } from '../types';

export type AppointmentCalendarEvent = CalendarEvent & { appointment: Appointment };

export type AppointmentPalette = {
    main: string;
    container: string;
    onContainer: string;
};

const CANCELLED_CALENDAR_ID = 'cancelled';

function calendarIdFor(appointment: Appointment): string {
    return isCancelled(appointment) ? CANCELLED_CALENDAR_ID : appointment.service.color;
}

export function appointmentsToEvents(
    appointments: Appointment[],
    timezone: string,
): AppointmentCalendarEvent[] {
    return appointments.map((appointment) => ({
        id: appointment.id,
        calendarId: calendarIdFor(appointment),
        start: Temporal.Instant.from(appointment.starts_at).toZonedDateTimeISO(timezone),
        end: Temporal.Instant.from(appointment.ends_at).toZonedDateTimeISO(timezone),
        title: appointment.customer.name,
        description: appointment.service.name,
        _options: { disableDND: isCancelled(appointment), disableResize: isCancelled(appointment) },
        appointment,
    }));
}

export function isAppointmentCalendarEvent(event: CalendarEvent): event is AppointmentCalendarEvent {
    return 'appointment' in event;
}

function servicePalette(color: ServiceColor): AppointmentPalette {
    return {
        main: `var(--service-${color})`,
        container: `var(--service-${color}-surface)`,
        onContainer: `var(--service-${color})`,
    };
}

const CANCELLED_PALETTE: AppointmentPalette = {
    main: 'var(--muted-foreground)',
    container: 'var(--muted)',
    onContainer: 'var(--muted-foreground)',
};

const APPOINTMENT_PALETTES: Record<string, AppointmentPalette> = {
    ...Object.fromEntries(SERVICE_COLORS.map((color) => [color, servicePalette(color)])),
    [CANCELLED_CALENDAR_ID]: CANCELLED_PALETTE,
};

export function appointmentPalette(appointment: Appointment): AppointmentPalette {
    return APPOINTMENT_PALETTES[calendarIdFor(appointment)];
}

export const APPOINTMENT_CALENDARS: Record<string, CalendarType> = Object.fromEntries(
    Object.entries(APPOINTMENT_PALETTES).map(([id, palette]) => [
        id,
        { colorName: id, lightColors: palette, darkColors: palette },
    ]),
);

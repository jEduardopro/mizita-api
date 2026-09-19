import 'temporal-polyfill/global';
import type { NumberValue } from '@/components/form/NumberField';
import { todayAsIsoDate } from '@/lib/time';
import { SLOT_MINUTES } from './appointment-slots';
import type { Appointment, AppointmentPayload, AppointmentService } from '../types';

const MINUTES_PER_DAY = 24 * 60;

export const REQUIRED_APPOINTMENT_FIELDS = [
    'service',
    'customer',
    'date',
    'startsAt',
    'endsAt',
] as const;

export type SelectedCustomer = { id: string; name: string };

export type AppointmentFormValues = {
    service: AppointmentService | null;
    customer: SelectedCustomer | null;
    staffMemberId: string;
    date: string;
    startsAt: string;
    endsAt: string;
    durationMinutes: NumberValue;
    notes: string;
};

export type AppointmentField = keyof AppointmentFormValues;

export type RequiredAppointmentField = (typeof REQUIRED_APPOINTMENT_FIELDS)[number];

export const serverFields: Record<AppointmentField, string> = {
    service: 'service_id',
    customer: 'customer_id',
    staffMemberId: 'staff_member_id',
    date: 'starts_at',
    startsAt: 'starts_at',
    endsAt: 'ends_at',
    durationMinutes: 'starts_at',
    notes: 'notes',
};

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

function minutesSinceMidnight(time: string): number {
    const [hourText, minuteText] = time.split(':');

    return Number(hourText) * 60 + Number(minuteText);
}

function timeFromMinutes(totalMinutes: number): string {
    const clamped = ((totalMinutes % MINUTES_PER_DAY) + MINUTES_PER_DAY) % MINUTES_PER_DAY;

    return `${pad(Math.floor(clamped / 60))}:${pad(clamped % 60)}`;
}

function toLocalDateAndTime(instant: string, timezone: string): { date: string; time: string } {
    const zoned = Temporal.Instant.from(instant).toZonedDateTimeISO(timezone);

    return {
        date: zoned.toPlainDate().toString(),
        time: zoned.toPlainTime().toString({ smallestUnit: 'minute' }),
    };
}

function toInstant(date: string, time: string, timezone: string): string {
    return Temporal.PlainDate.from(date)
        .toZonedDateTime({ timeZone: timezone, plainTime: Temporal.PlainTime.from(time) })
        .toInstant()
        .toString();
}

export function initialAppointmentValues(
    appointment: Appointment | null,
    timezone: string,
    prefillStartsAt: string | null,
): AppointmentFormValues {
    if (appointment !== null) {
        const starts = toLocalDateAndTime(appointment.starts_at, timezone);
        const ends = toLocalDateAndTime(appointment.ends_at, timezone);

        return {
            service: appointment.service,
            customer: { id: appointment.customer.id, name: appointment.customer.name },
            staffMemberId: appointment.staff_member.id,
            date: starts.date,
            startsAt: starts.time,
            endsAt: ends.time,
            durationMinutes: appointment.duration_minutes,
            notes: appointment.notes ?? '',
        };
    }

    if (prefillStartsAt !== null) {
        const starts = toLocalDateAndTime(prefillStartsAt, timezone);

        return {
            service: null,
            customer: null,
            staffMemberId: '',
            date: starts.date,
            startsAt: starts.time,
            endsAt: timeFromMinutes(minutesSinceMidnight(starts.time) + SLOT_MINUTES),
            durationMinutes: SLOT_MINUTES,
            notes: '',
        };
    }

    return {
        service: null,
        customer: null,
        staffMemberId: '',
        date: todayAsIsoDate(),
        startsAt: '',
        endsAt: '',
        durationMinutes: '',
        notes: '',
    };
}

function isBlank(value: AppointmentFormValues[RequiredAppointmentField]): boolean {
    return value === null || value === '';
}

export function missingRequiredFields(values: AppointmentFormValues): RequiredAppointmentField[] {
    return REQUIRED_APPOINTMENT_FIELDS.filter((field) => isBlank(values[field]));
}

export type AppointmentChange = {
    values: AppointmentFormValues;
    written: AppointmentField[];
};

export function withService(
    current: AppointmentFormValues,
    service: AppointmentService | null,
): AppointmentChange {
    if (service === null || current.startsAt === '') {
        return {
            values: { ...current, service, durationMinutes: service?.duration_minutes ?? '' },
            written: ['service', 'durationMinutes'],
        };
    }

    return {
        values: {
            ...current,
            service,
            durationMinutes: service.duration_minutes,
            endsAt: timeFromMinutes(
                minutesSinceMidnight(current.startsAt) + service.duration_minutes,
            ),
        },
        written: ['service', 'durationMinutes', 'endsAt'],
    };
}

export function withStartsAt(current: AppointmentFormValues, time: string): AppointmentChange {
    if (time === '' || current.durationMinutes === '') {
        return { values: { ...current, startsAt: time }, written: ['startsAt'] };
    }

    return {
        values: {
            ...current,
            startsAt: time,
            endsAt: timeFromMinutes(minutesSinceMidnight(time) + current.durationMinutes),
        },
        written: ['startsAt', 'endsAt'],
    };
}

export function withEndsAt(current: AppointmentFormValues, time: string): AppointmentChange {
    if (time === '' || current.startsAt === '') {
        return { values: { ...current, endsAt: time }, written: ['endsAt'] };
    }

    return {
        values: {
            ...current,
            endsAt: time,
            durationMinutes: Math.max(
                0,
                minutesSinceMidnight(time) - minutesSinceMidnight(current.startsAt),
            ),
        },
        written: ['endsAt', 'durationMinutes'],
    };
}

export function appointmentPayloadFrom(
    values: AppointmentFormValues,
    timezone: string,
): AppointmentPayload {
    const notes = values.notes.trim();

    return {
        customer_id: values.customer?.id ?? '',
        service_id: values.service?.id ?? '',
        staff_member_id: values.staffMemberId,
        starts_at: toInstant(values.date, values.startsAt, timezone),
        ends_at: values.endsAt === '' ? null : toInstant(values.date, values.endsAt, timezone),
        notes: notes === '' ? null : notes,
    };
}

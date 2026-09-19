import { errorCodeFrom } from '@/lib/http';
import type { Appointment, AppointmentCanceller } from '../types';

const STALE_APPOINTMENT_CODES: readonly string[] = [
    'appointment_already_cancelled',
    'appointment_not_found',
];

export const CANCELLED_BY_KEYS = {
    customer: 'calendar.appointment.cancelled.byCustomer',
    business: 'calendar.appointment.cancelled.byBusiness',
} as const satisfies Record<AppointmentCanceller, string>;

export function isCancelled(appointment: Appointment): boolean {
    return appointment.status === 'cancelled';
}

export function describesStaleAppointment(error: unknown): boolean {
    const code = errorCodeFrom(error);

    return code !== undefined && STALE_APPOINTMENT_CODES.includes(code);
}

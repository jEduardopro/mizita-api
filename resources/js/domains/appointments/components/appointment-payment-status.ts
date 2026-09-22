import type { Appointment } from '../types';

export function isPaid(appointment: Appointment): boolean {
    return appointment.payment_status === 'paid';
}

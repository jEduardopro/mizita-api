import { Check, CircleDashed, type LucideIcon } from 'lucide-react';
import type { Appointment, AppointmentPaymentStatus } from '../types';

export type AppointmentPaymentBadgeLabelKey =
    | 'calendar.appointment.paid.badge'
    | 'calendar.appointment.partiallyPaid.badge';

export type AppointmentPaymentBadge = {
    icon: LucideIcon;
    labelKey: AppointmentPaymentBadgeLabelKey;
    toneClassName: string;
};

export const PAID_BADGE: AppointmentPaymentBadge = {
    icon: Check,
    labelKey: 'calendar.appointment.paid.badge',
    toneClassName: 'bg-success/12 text-success',
};

const PARTIALLY_PAID_BADGE: AppointmentPaymentBadge = {
    icon: CircleDashed,
    labelKey: 'calendar.appointment.partiallyPaid.badge',
    toneClassName: 'bg-warning/12 text-warning',
};

const BADGES: Partial<Record<AppointmentPaymentStatus, AppointmentPaymentBadge>> = {
    paid: PAID_BADGE,
    partially_paid: PARTIALLY_PAID_BADGE,
};

export function appointmentPaymentBadge(appointment: Appointment): AppointmentPaymentBadge | null {
    if (appointment.payment_status === null) {
        return null;
    }

    return BADGES[appointment.payment_status] ?? null;
}

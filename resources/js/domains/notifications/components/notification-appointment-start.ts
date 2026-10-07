import type { NotificationHandlers, StaffNotification } from '../types';
import { dispatchByType } from './dispatch-by-type';

const APPOINTMENT_STARTS: NotificationHandlers<void, string | null> = {
    appointment_booked: ({ details }) => details.appointment.starts_at,
    staff_schedule_changed: () => null,
};

export function appointmentStartOf(notification: StaffNotification): string | null {
    return dispatchByType(notification, APPOINTMENT_STARTS, undefined);
}

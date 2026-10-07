import type { TFunction } from 'i18next';
import { useTranslation } from 'react-i18next';
import type {
    AppointmentBookedNotification,
    NotificationHandlers,
    StaffNotification,
    StaffScheduleChangedNotification,
} from '../types';
import { dispatchByType } from './dispatch-by-type';

function appointmentBookedMessage({ details }: AppointmentBookedNotification, t: TFunction<'admin'>): string {
    return t('notifications.message.booked', {
        customer: details.customer.name,
        service: details.appointment.service_name,
    });
}

function staffScheduleChangedMessage({ details }: StaffScheduleChangedNotification, t: TFunction<'admin'>): string {
    return t('notifications.message.scheduleChanged', { name: details.staff_member.name });
}

const MESSAGE_BUILDERS: NotificationHandlers<TFunction<'admin'>, string> = {
    appointment_booked: appointmentBookedMessage,
    staff_schedule_changed: staffScheduleChangedMessage,
};

export function useNotificationMessage(notification: StaffNotification): string {
    const { t } = useTranslation('admin');

    return dispatchByType(notification, MESSAGE_BUILDERS, t);
}

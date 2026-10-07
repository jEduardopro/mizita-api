import type { TFunction } from 'i18next';
import { useTranslation } from 'react-i18next';
import type { NotificationType, StaffNotification } from '../types';

type MessageSource = Pick<StaffNotification, 'type' | 'customer' | 'appointment' | 'staff_member'>;

type MessageBuilder = (source: MessageSource, t: TFunction<'admin'>) => string;

function appointmentBookedMessage({ customer, appointment }: MessageSource, t: TFunction<'admin'>): string {
    const customerName = customer?.name ?? t('notifications.unknownCustomer');

    if (appointment === null) {
        return t('notifications.message.bookedWithoutService', { customer: customerName });
    }

    return t('notifications.message.booked', {
        customer: customerName,
        service: appointment.service_name,
    });
}

function staffScheduleChangedMessage({ staff_member }: MessageSource, t: TFunction<'admin'>): string {
    return t('notifications.message.scheduleChanged', {
        name: staff_member?.name ?? t('notifications.unknownStaffMember'),
    });
}

const MESSAGE_BUILDERS: Record<NotificationType, MessageBuilder> = {
    appointment_booked: appointmentBookedMessage,
    staff_schedule_changed: staffScheduleChangedMessage,
};

export function useNotificationMessage(source: MessageSource): string {
    const { t } = useTranslation('admin');

    return MESSAGE_BUILDERS[source.type](source, t);
}

import { useTranslation } from 'react-i18next';
import type { StaffNotification } from '../types';

type MessageSource = Pick<StaffNotification, 'customer' | 'appointment'>;

export function useNotificationMessage({ customer, appointment }: MessageSource): string {
    const { t } = useTranslation('admin');
    const customerName = customer?.name ?? t('notifications.unknownCustomer');

    if (appointment === null) {
        return t('notifications.message.bookedWithoutService', { customer: customerName });
    }

    return t('notifications.message.booked', {
        customer: customerName,
        service: appointment.service_name,
    });
}

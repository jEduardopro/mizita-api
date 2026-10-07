import { useTranslation } from 'react-i18next';
import type { StaffNotification } from '../types';
import { longDate, timeRange } from './notification-dates';

export type NotificationDetailField = {
    id: string;
    label: string;
    value: string;
    className?: string;
    href?: string;
};

type FieldSource = Pick<StaffNotification, 'appointment' | 'customer' | 'recipient'>;

export function useNotificationDetailFields(
    { appointment, customer, recipient }: FieldSource,
    timezone: string,
    customerHref?: string,
): NotificationDetailField[] {
    const { t, i18n } = useTranslation('admin');
    const fields: NotificationDetailField[] = [];

    if (customer !== null) {
        fields.push({
            id: 'customer',
            label: t('notifications.show.fields.customer'),
            value: customer.name,
            href: customerHref,
        });
    }

    if (appointment !== null) {
        fields.push(
            { id: 'service', label: t('notifications.show.fields.service'), value: appointment.service_name },
            {
                id: 'date',
                label: t('notifications.show.fields.date'),
                value: longDate(appointment.starts_at, timezone, i18n.language),
                className: 'first-letter:uppercase',
            },
            {
                id: 'time',
                label: t('notifications.show.fields.time'),
                value: timeRange(appointment.starts_at, appointment.ends_at, timezone),
                className: 'tabular-nums',
            },
        );
    }

    if (appointment?.reference_code) {
        fields.push({
            id: 'reference',
            label: t('notifications.show.fields.reference'),
            value: appointment.reference_code,
            className: 'font-mono tracking-wide',
        });
    }

    fields.push({ id: 'recipient', label: t('notifications.show.fields.recipient'), value: recipient.name });

    return fields;
}

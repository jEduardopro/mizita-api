import type { TFunction } from 'i18next';
import { useTranslation } from 'react-i18next';
import type {
    AppointmentBookedNotification,
    NotificationHandlers,
    StaffNotification,
    StaffScheduleChangedNotification,
} from '../types';
import { dispatchByType } from './dispatch-by-type';
import { longDate, timeRange } from './notification-dates';

export type NotificationDetailField = {
    id: string;
    label: string;
    value: string;
    className?: string;
    href?: string;
};

export type NotificationDetailLinks = {
    customerHref?: string;
    staffMemberHref?: string;
};

type FieldContext = NotificationDetailLinks & {
    t: TFunction<'admin'>;
    locale: string;
    timezone: string;
};

function appointmentBookedFields(
    { details, recipient }: AppointmentBookedNotification,
    { t, locale, timezone, customerHref }: FieldContext,
): NotificationDetailField[] {
    const { appointment, customer } = details;

    return [
        {
            id: 'customer',
            label: t('notifications.show.fields.customer'),
            value: customer.name,
            href: customerHref,
        },
        { id: 'service', label: t('notifications.show.fields.service'), value: appointment.service_name },
        {
            id: 'date',
            label: t('notifications.show.fields.date'),
            value: longDate(appointment.starts_at, timezone, locale),
            className: 'first-letter:uppercase',
        },
        {
            id: 'time',
            label: t('notifications.show.fields.time'),
            value: timeRange(appointment.starts_at, appointment.ends_at, timezone),
            className: 'tabular-nums',
        },
        {
            id: 'reference',
            label: t('notifications.show.fields.reference'),
            value: appointment.reference_code,
            className: 'font-mono tracking-wide',
        },
        { id: 'recipient', label: t('notifications.show.fields.recipient'), value: recipient.name },
    ];
}

function staffScheduleChangedFields(
    { details }: StaffScheduleChangedNotification,
    { t, staffMemberHref }: FieldContext,
): NotificationDetailField[] {
    return [
        {
            id: 'staffMember',
            label: t('notifications.show.fields.staffMember'),
            value: details.staff_member.name,
            href: staffMemberHref,
        },
    ];
}

const FIELDS_BUILDERS: NotificationHandlers<FieldContext, NotificationDetailField[]> = {
    appointment_booked: appointmentBookedFields,
    staff_schedule_changed: staffScheduleChangedFields,
};

export function useNotificationDetailFields(
    notification: StaffNotification,
    timezone: string,
    links: NotificationDetailLinks,
): NotificationDetailField[] {
    const { t, i18n } = useTranslation('admin');

    return dispatchByType(notification, FIELDS_BUILDERS, { ...links, t, locale: i18n.language, timezone });
}

import type { TFunction } from 'i18next';
import { useTranslation } from 'react-i18next';
import type { NotificationType, StaffNotification } from '../types';
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

type FieldSource = Pick<StaffNotification, 'type' | 'appointment' | 'customer' | 'recipient' | 'staff_member'>;

type FieldContext = NotificationDetailLinks & {
    t: TFunction<'admin'>;
    locale: string;
    timezone: string;
};

type FieldsBuilder = (source: FieldSource, context: FieldContext) => NotificationDetailField[];

function appointmentBookedFields(
    { appointment, customer, recipient }: FieldSource,
    { t, locale, timezone, customerHref }: FieldContext,
): NotificationDetailField[] {
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
                value: longDate(appointment.starts_at, timezone, locale),
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

function staffScheduleChangedFields(
    { staff_member }: FieldSource,
    { t, staffMemberHref }: FieldContext,
): NotificationDetailField[] {
    if (staff_member === null) {
        return [];
    }

    return [
        {
            id: 'staffMember',
            label: t('notifications.show.fields.staffMember'),
            value: staff_member.name,
            href: staffMemberHref,
        },
    ];
}

const FIELDS_BUILDERS: Record<NotificationType, FieldsBuilder> = {
    appointment_booked: appointmentBookedFields,
    staff_schedule_changed: staffScheduleChangedFields,
};

export function useNotificationDetailFields(
    source: FieldSource,
    timezone: string,
    links: NotificationDetailLinks,
): NotificationDetailField[] {
    const { t, i18n } = useTranslation('admin');

    return FIELDS_BUILDERS[source.type](source, { ...links, t, locale: i18n.language, timezone });
}

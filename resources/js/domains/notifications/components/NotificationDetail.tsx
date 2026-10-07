import { Link } from '@inertiajs/react';
import { cn } from 'cn';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import type { StaffNotification } from '../types';
import { NotificationDateLeaf } from './NotificationDateLeaf';
import { dateAndTime } from './notification-dates';
import {
    type NotificationDetailField,
    type NotificationDetailLinks,
    useNotificationDetailFields,
} from './use-notification-detail-fields';
import { useNotificationMessage } from './use-notification-message';

const FIELD_LINK_CLASS =
    'relative inline-block max-w-full rounded-sm underline decoration-muted-foreground/50 underline-offset-4 outline-none after:absolute after:-inset-x-1 after:-inset-y-3 hover:decoration-current focus-visible:decoration-current focus-visible:ring-3 focus-visible:ring-ring/50 md:after:-inset-y-1';

type FieldValueProps = Pick<NotificationDetailField, 'value' | 'href'>;

function FieldValue({ value, href }: FieldValueProps) {
    if (href === undefined) {
        return value;
    }

    return (
        <Link href={href} className={FIELD_LINK_CLASS}>
            {value}
        </Link>
    );
}

type Props = NotificationDetailLinks & {
    notification: StaffNotification;
    timezone: string;
    headerAction?: ReactNode;
};

export function NotificationDetail({ notification, timezone, customerHref, staffMemberHref, headerAction }: Props) {
    const { t, i18n } = useTranslation('admin');
    const message = useNotificationMessage(notification);
    const fields = useNotificationDetailFields(notification, timezone, { customerHref, staffMemberHref });
    const { appointment } = notification;
    const appointmentGone = notification.type === 'appointment_booked' && appointment === null;

    return (
        <article className="grid max-w-2xl gap-6">
            <header className="flex flex-col gap-4 sm:flex-row sm:items-start">
                <div className="flex min-w-0 flex-1 items-start gap-4">
                    <NotificationDateLeaf
                        startsAt={appointment?.starts_at ?? null}
                        timezone={timezone}
                        size="lg"
                    />

                    <div className="grid min-w-0 gap-1 pt-1">
                        <h2 className="text-lg font-semibold text-pretty sm:text-xl">{message}</h2>

                        <p className="text-sm text-muted-foreground tabular-nums">
                            <time dateTime={notification.created_at}>
                                {t('notifications.show.received', {
                                    when: dateAndTime(notification.created_at, timezone, i18n.language),
                                })}
                            </time>
                        </p>
                    </div>
                </div>

                {headerAction === undefined || headerAction === null ? null : (
                    <div className="flex shrink-0 sm:pt-1">{headerAction}</div>
                )}
            </header>

            {appointmentGone ? (
                <p className="text-sm text-muted-foreground">{t('notifications.show.appointmentGone')}</p>
            ) : null}

            {fields.length === 0 ? null : (
                <dl className="grid gap-4 rounded-xl border border-border bg-card p-4 sm:grid-cols-2 sm:p-5">
                    {fields.map((field) => (
                        <div key={field.id} className="grid min-w-0 gap-0.5">
                            <dt className="text-xs text-muted-foreground">{field.label}</dt>
                            <dd className={cn('text-sm font-medium break-words', field.className)}>
                                <FieldValue value={field.value} href={field.href} />
                            </dd>
                        </div>
                    ))}
                </dl>
            )}
        </article>
    );
}

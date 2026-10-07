import { CalendarDays, LoaderCircle } from 'lucide-react';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { AppointmentDetailsLauncher } from '@/domains/appointments/components/AppointmentDetailsLauncher';
import { useAppointment, useRefreshAppointments } from '@/domains/appointments/queries';
import type { Appointment } from '@/domains/appointments/types';
import { useBusinessTimezone } from '@/domains/businesses/queries';
import { customerShowUrl } from '@/domains/customers/components/customer-urls';
import { NotificationDetail } from '@/domains/notifications/components/NotificationDetail';
import { NotificationDetailSkeleton } from '@/domains/notifications/components/NotificationDetailSkeleton';
import { NotificationLoadError } from '@/domains/notifications/components/NotificationLoadError';
import { NOTIFICATIONS_URL, notificationShowUrl } from '@/domains/notifications/components/notification-urls';
import { useMarkAsReadOnView } from '@/domains/notifications/components/use-mark-as-read-on-view';
import { useNotification } from '@/domains/notifications/queries';
import type { StaffNotification } from '@/domains/notifications/types';
import { AppointmentChargeLauncher } from '@/domains/payments/components/AppointmentChargeLauncher';
import { AppointmentPaymentPanel } from '@/domains/payments/components/AppointmentPaymentPanel';
import { useAuthorization } from '@/hooks/use-authorization';
import { useBusinessCurrency } from '@/hooks/use-business-currency';
import { useErrorToast } from '@/hooks/use-error-toast';
import { AdminLayout } from '@/layouts/AdminLayout';
import { formMessageFrom, httpStatusFrom } from '@/lib/http';
import { centsFromDecimalString } from '@/lib/money';
import { withReturnTo } from '@/lib/return-to';

const NOT_FOUND_STATUS = 404;

const ACTION_CLASS = 'h-11 px-4 md:h-9';

type AppointmentActionProps = {
    appointmentId: string;
    timezone: string;
};

function ViewAppointmentAction({ appointmentId, timezone }: AppointmentActionProps) {
    const { t } = useTranslation('admin');
    const appointment = useAppointment(appointmentId);
    const currencyCode = useBusinessCurrency();
    const refreshAppointments = useRefreshAppointments();
    const errorToast = useErrorToast();
    const [opening, setOpening] = useState(false);
    const [appointmentToCharge, setAppointmentToCharge] = useState<Appointment | null>(null);

    const renderPaymentPanel = useCallback(
        (target: Appointment) => (
            <AppointmentPaymentPanel
                appointmentId={target.id}
                customerName={target.customer.name}
                currencyCode={currencyCode}
                timezone={timezone}
                onChanged={refreshAppointments}
            />
        ),
        [currencyCode, timezone, refreshAppointments],
    );

    function openAppointment() {
        if (appointment.isError) {
            errorToast.show(formMessageFrom(appointment.error, t('notifications.show.appointmentFailed')));
            void appointment.refetch();

            return;
        }

        setOpening(true);
    }

    const waiting = opening && appointment.isPending;

    return (
        <>
            <Button type="button" variant="brand" disabled={waiting} onClick={openAppointment} className={ACTION_CLASS}>
                {waiting ? (
                    <LoaderCircle aria-hidden="true" className="motion-safe:animate-spin" />
                ) : (
                    <CalendarDays aria-hidden="true" />
                )}
                {t('notifications.actions.viewAppointment')}
            </Button>

            <AppointmentDetailsLauncher
                appointment={opening ? (appointment.data ?? null) : null}
                timezone={timezone}
                onClose={() => setOpening(false)}
                onCharge={setAppointmentToCharge}
                renderPaymentPanel={renderPaymentPanel}
            />

            {appointmentToCharge !== null ? (
                <AppointmentChargeLauncher
                    appointmentId={appointmentToCharge.id}
                    customerName={appointmentToCharge.customer.name}
                    serviceLine={{
                        name: appointmentToCharge.service.name,
                        color: appointmentToCharge.service.color,
                        priceCents: centsFromDecimalString(appointmentToCharge.service.price),
                    }}
                    currencyCode={currencyCode}
                    hasPayment={appointmentToCharge.payment_status !== null}
                    onClose={() => setAppointmentToCharge(null)}
                    onPaid={() => {
                        setAppointmentToCharge(null);
                        refreshAppointments();
                    }}
                />
            ) : null}
        </>
    );
}

function customerHrefFor({ id, customer }: Pick<StaffNotification, 'id' | 'customer'>): string | undefined {
    if (customer === null) {
        return undefined;
    }

    return withReturnTo(customerShowUrl(customer.id), notificationShowUrl(id));
}

type Props = {
    notificationId: string;
};

export default function ShowNotification({ notificationId }: Props) {
    const { t } = useTranslation('admin');
    const { can } = useAuthorization();
    const notification = useNotification(notificationId);
    const timezone = useBusinessTimezone();

    useMarkAsReadOnView(notification.data);

    const title = t('notifications.show.title');
    const isLoading = notification.isPending || (notification.isSuccess && timezone === null);

    return (
        <AdminLayout
            title={title}
            breadcrumbs={[{ label: t('notifications.title'), href: NOTIFICATIONS_URL }, { label: title }]}
        >
            {isLoading ? <NotificationDetailSkeleton /> : null}

            {notification.isError ? (
                <NotificationLoadError
                    notFound={httpStatusFrom(notification.error) === NOT_FOUND_STATUS}
                    onRetry={() => void notification.refetch()}
                />
            ) : null}

            {notification.isSuccess && timezone !== null ? (
                <NotificationDetail
                    notification={notification.data}
                    timezone={timezone}
                    customerHref={
                        can('view_customers') ? customerHrefFor(notification.data) : undefined
                    }
                    headerAction={
                        notification.data.appointment !== null && can('view_appointments') ? (
                            <ViewAppointmentAction
                                appointmentId={notification.data.appointment.id}
                                timezone={timezone}
                            />
                        ) : undefined
                    }
                />
            ) : null}
        </AdminLayout>
    );
}

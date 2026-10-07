import { router } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { Breadcrumb } from '@/components/admin/shell/AdminBreadcrumbs';
import { AppointmentDetailsLauncher } from '@/domains/appointments/components/AppointmentDetailsLauncher';
import { CustomerAppointmentsTimeline } from '@/domains/appointments/components/CustomerAppointmentsTimeline';
import { NewAppointmentDialog } from '@/domains/appointments/components/NewAppointmentDialog';
import {
    useCustomerLastAppointment,
    useRefreshAppointments,
} from '@/domains/appointments/queries';
import type { Appointment } from '@/domains/appointments/types';
import { DEFAULT_CURRENCY_CODE } from '@/domains/businesses/components/settings/location-options';
import { useCalendarSettings } from '@/domains/businesses/queries';
import { CustomerAboutPanel } from '@/domains/customers/components/CustomerAboutPanel';
import { CustomerLastAppointment } from '@/domains/customers/components/CustomerLastAppointment';
import { CustomerLoadError } from '@/domains/customers/components/CustomerLoadError';
import { CustomerNotesPanel } from '@/domains/customers/components/CustomerNotesPanel';
import { CustomerShowActions } from '@/domains/customers/components/CustomerShowActions';
import { CustomerShowHeader } from '@/domains/customers/components/CustomerShowHeader';
import { CustomerShowSkeleton } from '@/domains/customers/components/CustomerShowSkeleton';
import { CustomerShowTabs } from '@/domains/customers/components/CustomerShowTabs';
import { CUSTOMERS_URL } from '@/domains/customers/components/customer-urls';
import { useCustomerShowTab } from '@/domains/customers/components/use-customer-show-tab';
import { useCustomer } from '@/domains/customers/queries';
import {
    isNotificationShowUrl,
    NOTIFICATIONS_URL,
} from '@/domains/notifications/components/notification-urls';
import { AppointmentChargeLauncher } from '@/domains/payments/components/AppointmentChargeLauncher';
import { AppointmentPaymentPanel } from '@/domains/payments/components/AppointmentPaymentPanel';
import { useUrlQueryState } from '@/hooks/use-url-query-state';
import { AdminLayout } from '@/layouts/AdminLayout';
import { httpStatusFrom } from '@/lib/http';
import { centsFromDecimalString } from '@/lib/money';
import { RETURN_PARAMETER, safeReturnTo } from '@/lib/return-to';
import { resolvedTimezone } from '@/lib/timezone';

const NOT_FOUND_STATUS = 404;

function useCustomerBreadcrumbs(customerName: string): Breadcrumb[] {
    const { t } = useTranslation('admin');
    const returnTo = safeReturnTo(useUrlQueryState().read(RETURN_PARAMETER), CUSTOMERS_URL);

    if (isNotificationShowUrl(returnTo)) {
        return [
            { label: t('notifications.title'), href: NOTIFICATIONS_URL },
            { label: t('notifications.show.title'), href: returnTo },
            { label: customerName },
        ];
    }

    return [{ label: t('customers.title'), href: CUSTOMERS_URL }, { label: customerName }];
}

type Props = {
    customerId: string;
};

export default function ShowCustomer({ customerId }: Props) {
    const { t } = useTranslation('admin');
    const customer = useCustomer(customerId);
    const { data: lastAppointment } = useCustomerLastAppointment(customerId);
    const { data: calendarSettings } = useCalendarSettings();
    const { tab, setTab } = useCustomerShowTab();
    const [booking, setBooking] = useState(false);
    const [appointmentToCharge, setAppointmentToCharge] = useState<Appointment | null>(null);
    const [selectedAppointment, setSelectedAppointment] = useState<Appointment | null>(null);
    const refreshAppointments = useRefreshAppointments();

    const timezone = calendarSettings?.timezone ?? resolvedTimezone();
    const currencyCode = calendarSettings?.currency_code ?? DEFAULT_CURRENCY_CODE;
    const title = customer.data?.name ?? t('customers.show.title');
    const breadcrumbs = useCustomerBreadcrumbs(title);

    const renderPaymentPanel = useCallback(
        (appointment: Appointment) => (
            <AppointmentPaymentPanel
                appointmentId={appointment.id}
                customerName={appointment.customer.name}
                currencyCode={currencyCode}
                timezone={timezone}
                onChanged={refreshAppointments}
            />
        ),
        [currencyCode, timezone, refreshAppointments],
    );

    return (
        <AdminLayout title={title} breadcrumbs={breadcrumbs}>
            {customer.isPending ? <CustomerShowSkeleton /> : null}

            {customer.isError ? (
                <CustomerLoadError
                    notFound={httpStatusFrom(customer.error) === NOT_FOUND_STATUS}
                    onRetry={() => void customer.refetch()}
                />
            ) : null}

            {customer.data ? (
                <div className="grid gap-6">
                    <CustomerShowHeader
                        name={customer.data.name}
                        photoUrl={customer.data.photo_url}
                        lastAppointment={
                            lastAppointment ? (
                                <CustomerLastAppointment
                                    startsAt={lastAppointment.starts_at}
                                    timezone={timezone}
                                    serviceName={lastAppointment.service.name}
                                    serviceColor={lastAppointment.service.color}
                                    staffName={lastAppointment.staff_member.name}
                                    onOpen={() => setSelectedAppointment(lastAppointment)}
                                />
                            ) : null
                        }
                        actions={
                            <CustomerShowActions
                                customer={customer.data}
                                onBook={() => setBooking(true)}
                                onDeleted={() => router.visit(CUSTOMERS_URL)}
                            />
                        }
                    />

                    <CustomerShowTabs
                        value={tab}
                        onValueChange={setTab}
                        about={<CustomerAboutPanel customer={customer.data} />}
                        notes={<CustomerNotesPanel notes={customer.data.notes} />}
                        appointments={
                            <CustomerAppointmentsTimeline
                                customerId={customer.data.id}
                                timezone={timezone}
                                onCharge={setAppointmentToCharge}
                                renderPaymentPanel={renderPaymentPanel}
                            />
                        }
                    />

                    <NewAppointmentDialog
                        mode="create"
                        open={booking}
                        onOpenChange={setBooking}
                        appointment={null}
                        timezone={timezone}
                        initialCustomer={{ id: customer.data.id, name: customer.data.name }}
                    />

                    <AppointmentDetailsLauncher
                        appointment={selectedAppointment}
                        timezone={timezone}
                        onClose={() => setSelectedAppointment(null)}
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
                                priceCents: centsFromDecimalString(
                                    appointmentToCharge.service.price,
                                ),
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
                </div>
            ) : null}
        </AdminLayout>
    );
}

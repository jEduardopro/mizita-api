import { router } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { CustomerAppointmentsTimeline } from '@/domains/appointments/components/CustomerAppointmentsTimeline';
import { NewAppointmentDialog } from '@/domains/appointments/components/NewAppointmentDialog';
import { useRefreshAppointments } from '@/domains/appointments/queries';
import type { Appointment } from '@/domains/appointments/types';
import { DEFAULT_CURRENCY_CODE } from '@/domains/businesses/components/settings/location-options';
import { useBusinessSettings } from '@/domains/businesses/queries';
import { CustomerAboutPanel } from '@/domains/customers/components/CustomerAboutPanel';
import { CustomerLoadError } from '@/domains/customers/components/CustomerLoadError';
import { CustomerNotesPanel } from '@/domains/customers/components/CustomerNotesPanel';
import { CustomerShowActions } from '@/domains/customers/components/CustomerShowActions';
import { CustomerShowHeader } from '@/domains/customers/components/CustomerShowHeader';
import { CustomerShowSkeleton } from '@/domains/customers/components/CustomerShowSkeleton';
import { CustomerShowTabs } from '@/domains/customers/components/CustomerShowTabs';
import { CUSTOMERS_URL } from '@/domains/customers/components/customer-urls';
import { useCustomerShowTab } from '@/domains/customers/components/use-customer-show-tab';
import { useCustomer } from '@/domains/customers/queries';
import { AppointmentChargeLauncher } from '@/domains/payments/components/AppointmentChargeLauncher';
import { AppointmentPaymentPanel } from '@/domains/payments/components/AppointmentPaymentPanel';
import { AdminLayout } from '@/layouts/AdminLayout';
import { httpStatusFrom } from '@/lib/http';
import { centsFromDecimalString } from '@/lib/money';
import { resolvedTimezone } from '@/lib/timezone';

const NOT_FOUND_STATUS = 404;

type Props = {
    customerId: string;
};

export default function ShowCustomer({ customerId }: Props) {
    const { t } = useTranslation('admin');
    const customer = useCustomer(customerId);
    const { data: businessSettings } = useBusinessSettings();
    const { tab, setTab } = useCustomerShowTab();
    const [booking, setBooking] = useState(false);
    const [appointmentToCharge, setAppointmentToCharge] = useState<Appointment | null>(null);
    const refreshAppointments = useRefreshAppointments();

    const timezone = businessSettings?.timezone ?? resolvedTimezone();
    const currencyCode = businessSettings?.currency_code ?? DEFAULT_CURRENCY_CODE;
    const title = customer.data?.name ?? t('customers.show.title');

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
        <AdminLayout
            title={title}
            breadcrumbs={[{ label: t('customers.title'), href: CUSTOMERS_URL }, { label: title }]}
        >
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

import { CreditCard } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { isPaid } from './appointment-payment-status';
import { isCancelled } from './appointment-status';
import { AppointmentDetailsActions } from './AppointmentDetailsActions';
import { AppointmentDetailsBody } from './AppointmentDetailsBody';
import { AppointmentDetailsTabs } from './AppointmentDetailsTabs';
import { AppointmentPaidBadge } from './AppointmentPaidBadge';
import { CancelAppointmentDialog } from './CancelAppointmentDialog';
import type { Appointment } from '../types';

type Props = {
    appointment: Appointment | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    timezone: string;
    onEdit: (appointment: Appointment) => void;
    onDelete: (appointment: Appointment) => void;
    onCharge?: (appointment: Appointment) => void;
    renderPaymentPanel?: (appointment: Appointment) => ReactNode;
};

type ChargeActionProps = {
    appointment: Appointment;
    onCharge: (appointment: Appointment) => void;
};

function AppointmentChargeAction({ appointment, onCharge }: ChargeActionProps) {
    const { t } = useTranslation('admin');

    if (isCancelled(appointment) || isPaid(appointment)) {
        return null;
    }

    return (
        <Button
            type="button"
            variant="brand"
            onClick={() => onCharge(appointment)}
            className="ms-auto h-11 px-4 md:h-9"
        >
            <CreditCard aria-hidden="true" />
            {t('calendar.appointment.actions.charge')}
        </Button>
    );
}

type SheetBodyProps = {
    appointment: Appointment;
    timezone: string;
    renderPaymentPanel?: (appointment: Appointment) => ReactNode;
};

function AppointmentDetailsSheetBody({ appointment, timezone, renderPaymentPanel }: SheetBodyProps) {
    const details = <AppointmentDetailsBody appointment={appointment} timezone={timezone} />;

    if (renderPaymentPanel === undefined) {
        return (
            <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 pb-4">
                {details}
            </div>
        );
    }

    return (
        <AppointmentDetailsTabs
            key={appointment.id}
            details={details}
            payments={renderPaymentPanel(appointment)}
        />
    );
}

export function AppointmentDetailsPopover({
    appointment,
    open,
    onOpenChange,
    timezone,
    onEdit,
    onDelete,
    onCharge,
    renderPaymentPanel,
}: Props) {
    const { t } = useTranslation('admin');
    const [appointmentToCancel, setAppointmentToCancel] = useState<Appointment | null>(null);

    const cancelled = appointment !== null && isCancelled(appointment);

    function requestCancel(target: Appointment) {
        onOpenChange(false);
        setAppointmentToCancel(target);
    }

    return (
        <>
            <Sheet open={open} onOpenChange={onOpenChange}>
                <SheetContent
                    side="bottom"
                    className="flex flex-col pb-[env(safe-area-inset-bottom)] data-[side=bottom]:h-[85svh]"
                >
                    <SheetHeader className="shrink-0 pr-14">
                        <div className="flex flex-wrap items-center gap-2">
                            <SheetTitle>{t('calendar.appointment.details.title')}</SheetTitle>

                            {appointment !== null ? (
                                <AppointmentPaidBadge appointment={appointment} />
                            ) : null}

                            {appointment !== null && onCharge !== undefined ? (
                                <AppointmentChargeAction
                                    appointment={appointment}
                                    onCharge={onCharge}
                                />
                            ) : null}
                        </div>
                    </SheetHeader>

                    {appointment !== null ? (
                        <AppointmentDetailsSheetBody
                            appointment={appointment}
                            timezone={timezone}
                            renderPaymentPanel={renderPaymentPanel}
                        />
                    ) : null}

                    {cancelled ? (
                        <p className="px-4 text-sm text-muted-foreground">
                            {t('calendar.appointment.cancelled.locked')}
                        </p>
                    ) : null}

                    {appointment !== null ? (
                        <SheetFooter>
                            <AppointmentDetailsActions
                                appointment={appointment}
                                onEdit={() => onEdit(appointment)}
                                onCancel={() => requestCancel(appointment)}
                                onDelete={() => onDelete(appointment)}
                            />
                        </SheetFooter>
                    ) : null}
                </SheetContent>
            </Sheet>

            {appointmentToCancel !== null ? (
                <CancelAppointmentDialog
                    appointment={appointmentToCancel}
                    open
                    onOpenChange={(next) => {
                        if (! next) {
                            setAppointmentToCancel(null);
                        }
                    }}
                />
            ) : null}
        </>
    );
}

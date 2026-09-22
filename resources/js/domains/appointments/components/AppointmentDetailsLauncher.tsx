import { useState, type ReactNode } from 'react';
import { AppointmentDetailsPopover } from './AppointmentDetailsPopover';
import { DeleteAppointmentDialog } from './DeleteAppointmentDialog';
import { NewAppointmentDialog } from './NewAppointmentDialog';
import type { Appointment } from '../types';

type Props = {
    appointment: Appointment | null;
    timezone: string;
    onClose: () => void;
    onCharge?: (appointment: Appointment) => void;
    renderPaymentPanel?: (appointment: Appointment) => ReactNode;
};

export function AppointmentDetailsLauncher({
    appointment,
    timezone,
    onClose,
    onCharge,
    renderPaymentPanel,
}: Props) {
    const [appointmentToEdit, setAppointmentToEdit] = useState<Appointment | null>(null);
    const [appointmentToDelete, setAppointmentToDelete] = useState<Appointment | null>(null);

    const requestCharge =
        onCharge === undefined
            ? undefined
            : (target: Appointment) => {
                  onClose();
                  onCharge(target);
              };

    return (
        <>
            <AppointmentDetailsPopover
                appointment={appointment}
                open={appointment !== null}
                onOpenChange={(open) => {
                    if (! open) {
                        onClose();
                    }
                }}
                timezone={timezone}
                onEdit={(target) => {
                    onClose();
                    setAppointmentToEdit(target);
                }}
                onDelete={(target) => {
                    onClose();
                    setAppointmentToDelete(target);
                }}
                onCharge={requestCharge}
                renderPaymentPanel={renderPaymentPanel}
            />

            <NewAppointmentDialog
                mode="edit"
                open={appointmentToEdit !== null}
                onOpenChange={(open) => {
                    if (! open) {
                        setAppointmentToEdit(null);
                    }
                }}
                appointment={appointmentToEdit}
                timezone={timezone}
            />

            {appointmentToDelete !== null ? (
                <DeleteAppointmentDialog
                    appointment={appointmentToDelete}
                    open={appointmentToDelete !== null}
                    onOpenChange={(open) => {
                        if (! open) {
                            setAppointmentToDelete(null);
                        }
                    }}
                />
            ) : null}
        </>
    );
}

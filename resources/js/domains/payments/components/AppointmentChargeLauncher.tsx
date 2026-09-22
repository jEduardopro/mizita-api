import type { ChargeServiceLine } from './charge-form-values';
import { ChargeAppointmentDialog } from './ChargeAppointmentDialog';
import { useAppointmentPayment } from '../queries';

type Props = {
    appointmentId: string;
    customerName: string;
    serviceLine: ChargeServiceLine;
    currencyCode: string;
    hasPayment: boolean;
    onClose: () => void;
    onPaid: () => void;
};

export function AppointmentChargeLauncher({
    appointmentId,
    customerName,
    serviceLine,
    currencyCode,
    hasPayment,
    onClose,
    onPaid,
}: Props) {
    const payment = useAppointmentPayment(appointmentId, hasPayment);

    if (payment.isLoading) {
        return null;
    }

    return (
        <ChargeAppointmentDialog
            open
            onOpenChange={(next) => {
                if (! next) {
                    onClose();
                }
            }}
            appointmentId={appointmentId}
            customerName={customerName}
            serviceLine={serviceLine}
            currencyCode={currencyCode}
            existingPayment={hasPayment ? (payment.data ?? null) : null}
            onPaid={onPaid}
        />
    );
}

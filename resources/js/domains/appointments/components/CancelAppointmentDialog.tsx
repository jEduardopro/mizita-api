import { CalendarX2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogMedia,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { useServerErrors } from '@/hooks/use-server-errors';
import { raiseSuccessToast } from '@/lib/toast';
import { describesStaleAppointment } from './appointment-status';
import { useCancelAppointment } from '../queries';
import type { Appointment } from '../types';

type Props = {
    appointment: Appointment;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function CancelAppointmentDialog({ appointment, open, onOpenChange }: Props) {
    const { t } = useTranslation('admin');
    const cancelAppointment = useCancelAppointment();
    const serverErrors = useServerErrors();

    async function confirm() {
        try {
            await cancelAppointment.mutateAsync(appointment.id);
            raiseSuccessToast(t('calendar.appointment.toasts.cancelled'));
            onOpenChange(false);
        } catch (error) {
            serverErrors.capture(error, t('calendar.appointment.errors.cancelFailed'));

            if (describesStaleAppointment(error)) {
                onOpenChange(false);
            }
        }
    }

    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogMedia>
                        <CalendarX2 aria-hidden="true" />
                    </AlertDialogMedia>

                    <AlertDialogTitle>{t('calendar.appointment.cancel.title')}</AlertDialogTitle>

                    <AlertDialogDescription>
                        {t('calendar.appointment.cancel.description')}
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <AlertDialogFooter>
                    <AlertDialogCancel className="h-11 px-4 md:h-9">
                        {t('calendar.appointment.cancel.keep')}
                    </AlertDialogCancel>

                    <AlertDialogAction
                        disabled={cancelAppointment.isPending}
                        onClick={(event) => {
                            event.preventDefault();
                            void confirm();
                        }}
                        className="h-11 px-4 md:h-9"
                    >
                        {t('calendar.appointment.cancel.confirm')}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

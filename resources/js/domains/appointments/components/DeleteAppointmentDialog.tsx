import { Trash2 } from 'lucide-react';
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
import { formMessageFrom } from '@/lib/http';
import { raiseErrorToast, raiseSuccessToast } from '@/lib/toast';
import { useDeleteAppointment } from '../queries';
import type { Appointment } from '../types';

type Props = {
    appointment: Appointment;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function DeleteAppointmentDialog({ appointment, open, onOpenChange }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');
    const deleteAppointment = useDeleteAppointment();

    async function confirm() {
        try {
            await deleteAppointment.mutateAsync(appointment.id);
            raiseSuccessToast(t('calendar.appointment.toasts.deleted'));
            onOpenChange(false);
        } catch (error) {
            raiseErrorToast(formMessageFrom(error, t('calendar.appointment.errors.deleteFailed')));
        }
    }

    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogMedia>
                        <Trash2 aria-hidden="true" className="text-destructive" />
                    </AlertDialogMedia>

                    <AlertDialogTitle>{t('calendar.appointment.delete.title')}</AlertDialogTitle>

                    <AlertDialogDescription>
                        {t('calendar.appointment.delete.description')}
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <AlertDialogFooter>
                    <AlertDialogCancel className="h-11 px-4 md:h-9">
                        {tCommon('actions.cancel')}
                    </AlertDialogCancel>

                    <AlertDialogAction
                        variant="destructive"
                        disabled={deleteAppointment.isPending}
                        onClick={(event) => {
                            event.preventDefault();
                            void confirm();
                        }}
                        className="h-11 px-4 md:h-9"
                    >
                        {t('calendar.appointment.delete.confirm')}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

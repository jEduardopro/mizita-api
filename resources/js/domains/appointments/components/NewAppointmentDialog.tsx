import { useTranslation } from 'react-i18next';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { Sheet, SheetContent } from '@/components/ui/sheet';
import { useIsDesktop } from '@/hooks/use-is-desktop';
import { AppointmentDialogForm } from './AppointmentDialogForm';
import { AppointmentSheetForm } from './AppointmentSheetForm';
import type { AppointmentFormMode } from './use-appointment-form';
import type { AppointmentFormSurfaceProps } from './use-appointment-form-surface';
import type { Appointment } from '../types';

type Props = {
    mode: AppointmentFormMode;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    appointment: Appointment | null;
    timezone: string;
    prefillStartsAt?: string | null;
    initialCustomer?: { id: string; name: string } | null;
    onSaved?: (appointment: Appointment) => void;
};

export function NewAppointmentDialog({
    mode,
    open,
    onOpenChange,
    appointment,
    timezone,
    prefillStartsAt = null,
    initialCustomer = null,
    onSaved,
}: Props) {
    const { t } = useTranslation('admin');
    const isDesktop = useIsDesktop();

    const surface: AppointmentFormSurfaceProps = {
        mode,
        appointment,
        timezone,
        prefillStartsAt,
        initialCustomer,
        title:
            mode === 'edit'
                ? t('calendar.appointment.edit.title')
                : t('calendar.appointment.create.title'),
        actionLabel:
            mode === 'edit'
                ? t('calendar.appointment.actions.save')
                : t('calendar.appointment.actions.create'),
        onCancel: () => onOpenChange(false),
        onSaved: (saved) => {
            onOpenChange(false);
            onSaved?.(saved);
        },
    };

    if (isDesktop) {
        return (
            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent className="sm:max-w-lg">
                    <AppointmentDialogForm {...surface} />
                </DialogContent>
            </Dialog>
        );
    }

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side="bottom"
                className="flex max-h-[92svh] flex-col pb-[env(safe-area-inset-bottom)]"
            >
                <AppointmentSheetForm {...surface} />
            </SheetContent>
        </Sheet>
    );
}

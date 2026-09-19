import { DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { AppointmentFormActions } from './AppointmentFormActions';
import { AppointmentFormFields } from './AppointmentFormFields';
import { useAppointmentFormSurface, type AppointmentFormSurfaceProps } from './use-appointment-form-surface';

export function AppointmentDialogForm({
    title,
    actionLabel,
    onCancel,
    ...params
}: AppointmentFormSurfaceProps) {
    const { form, submit } = useAppointmentFormSurface(params);

    return (
        <form onSubmit={submit} className="grid gap-4">
            <DialogHeader>
                <DialogTitle>{title}</DialogTitle>
            </DialogHeader>

            <div className="max-h-[calc(100svh-12rem)] overflow-y-auto overscroll-contain pr-1">
                <AppointmentFormFields form={form} mode={params.mode} />
            </div>

            <DialogFooter>
                <AppointmentFormActions
                    actionLabel={actionLabel}
                    isSubmitting={form.isSubmitting}
                    onCancel={onCancel}
                />
            </DialogFooter>
        </form>
    );
}

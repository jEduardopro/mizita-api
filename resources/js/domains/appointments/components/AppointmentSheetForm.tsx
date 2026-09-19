import { SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { AppointmentFormActions } from './AppointmentFormActions';
import { AppointmentFormFields } from './AppointmentFormFields';
import { useAppointmentFormSurface, type AppointmentFormSurfaceProps } from './use-appointment-form-surface';

export function AppointmentSheetForm({ title, actionLabel, onCancel, ...params }: AppointmentFormSurfaceProps) {
    const { form, submit } = useAppointmentFormSurface(params);

    return (
        <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
            <SheetHeader>
                <SheetTitle>{title}</SheetTitle>
            </SheetHeader>

            <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4">
                <AppointmentFormFields form={form} mode={params.mode} />
            </div>

            <SheetFooter className="flex-row justify-end gap-2">
                <AppointmentFormActions
                    actionLabel={actionLabel}
                    isSubmitting={form.isSubmitting}
                    onCancel={onCancel}
                />
            </SheetFooter>
        </form>
    );
}

import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { TextareaField } from '@/components/form/TextareaField';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Sheet, SheetContent, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { useIsDesktop } from '@/hooks/use-is-desktop';
import { AppointmentCustomerField } from './AppointmentCustomerField';
import { AppointmentServiceField } from './AppointmentServiceField';
import { AppointmentStaffField } from './AppointmentStaffField';
import { AppointmentTimeFields } from './AppointmentTimeFields';
import type { AppointmentFormMode } from './use-appointment-form';
import { useAppointmentForm } from './use-appointment-form';
import type { Appointment } from '../types';

type Props = {
    mode: AppointmentFormMode;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    appointment: Appointment | null;
    timezone: string;
    prefillStartsAt?: string | null;
    onSaved?: (appointment: Appointment) => void;
};

export function NewAppointmentDialog({
    mode,
    open,
    onOpenChange,
    appointment,
    timezone,
    prefillStartsAt = null,
    onSaved,
}: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');
    const isDesktop = useIsDesktop();

    const form = useAppointmentForm({
        mode,
        appointment,
        timezone,
        prefillStartsAt,
        onSaved: (saved) => {
            onOpenChange(false);
            onSaved?.(saved);
        },
    });

    async function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        await form.submit();
    }

    const title =
        mode === 'edit' ? t('calendar.appointment.edit.title') : t('calendar.appointment.create.title');
    const actionLabel =
        mode === 'edit' ? t('calendar.appointment.actions.save') : t('calendar.appointment.actions.create');

    const fields = (
        <div className="grid gap-5">
            <AppointmentServiceField form={form} />
            <AppointmentCustomerField form={form} />
            <AppointmentStaffField form={form} mode={mode} />
            <AppointmentTimeFields form={form} />

            <TextareaField
                id="appointment-notes"
                label={t('calendar.appointment.form.notes.label')}
                placeholder={t('calendar.appointment.form.notes.placeholder')}
                hint={t('calendar.appointment.form.notes.hint')}
                rows={3}
                value={form.values.notes}
                onChange={(event) => form.update('notes', event.target.value)}
                error={form.errorFor('notes')}
            />
        </div>
    );

    const cancelButton = (
        <Button
            type="button"
            variant="outline"
            onClick={() => onOpenChange(false)}
            className="h-11 px-4 md:h-9"
        >
            {tCommon('actions.cancel')}
        </Button>
    );

    const submitButton = (
        <Button type="submit" variant="brand" disabled={form.isSubmitting} className="h-11 px-4 md:h-9">
            {actionLabel}
        </Button>
    );

    if (isDesktop) {
        return (
            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent className="sm:max-w-lg">
                    <form onSubmit={handleSubmit} className="grid gap-4">
                        <DialogHeader>
                            <DialogTitle>{title}</DialogTitle>
                        </DialogHeader>

                        <div className="max-h-[65svh] overflow-y-auto overscroll-contain pr-1">{fields}</div>

                        <DialogFooter>
                            {cancelButton}
                            {submitButton}
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        );
    }

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="bottom" className="flex max-h-[92svh] flex-col pb-[env(safe-area-inset-bottom)]">
                <form onSubmit={handleSubmit} className="flex min-h-0 flex-1 flex-col">
                    <SheetHeader>
                        <SheetTitle>{title}</SheetTitle>
                    </SheetHeader>

                    <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4">{fields}</div>

                    <SheetFooter className="flex-row justify-end gap-2">
                        {cancelButton}
                        {submitButton}
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}

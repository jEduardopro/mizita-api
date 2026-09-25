import { cn } from 'cn';
import { CalendarX2, Pencil, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { useAuthorization } from '@/hooks/use-authorization';
import { hasPayment } from './appointment-payment-status';
import { isCancelled } from './appointment-status';
import type { Appointment } from '../types';

export const APPOINTMENT_ACTION_PERMISSIONS = ['edit_appointment', 'delete_appointment'] as const;

type Props = {
    appointment: Appointment;
    onEdit: () => void;
    onCancel: () => void;
    onDelete: () => void;
};

export function AppointmentDetailsActions({ appointment, onEdit, onCancel, onDelete }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');
    const { can } = useAuthorization();

    const cancelled = isCancelled(appointment);
    const canEdit = can('edit_appointment');
    const canRemove = can('delete_appointment');
    const canDelete = canRemove && ! hasPayment(appointment);
    const canCancel = canRemove && ! cancelled;

    return (
        <div className="flex flex-wrap items-center justify-between gap-2 border-t border-border pt-3">
            {canDelete ? (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onDelete}
                    className="h-11 px-2.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive md:h-8"
                >
                    <Trash2 aria-hidden="true" />
                    {t('calendar.appointment.actions.delete')}
                </Button>
            ) : null}

            <div className={cn('flex flex-wrap justify-end gap-2', ! canDelete && 'ml-auto')}>
                {canCancel ? (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onCancel}
                        className="h-11 px-4 md:h-9"
                    >
                        <CalendarX2 aria-hidden="true" />
                        {t('calendar.appointment.actions.cancel')}
                    </Button>
                ) : null}

                {canEdit ? (
                    <Button
                        type="button"
                        variant="brand"
                        disabled={cancelled}
                        onClick={onEdit}
                        className="h-11 px-4 md:h-9"
                    >
                        <Pencil aria-hidden="true" />
                        {tCommon('actions.edit')}
                    </Button>
                ) : null}
            </div>
        </div>
    );
}

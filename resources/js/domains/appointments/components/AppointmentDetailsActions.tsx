import { CalendarX2, Pencil, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { isCancelled } from './appointment-status';
import type { Appointment } from '../types';

type Props = {
    appointment: Appointment;
    onEdit: () => void;
    onCancel: () => void;
    onDelete: () => void;
};

export function AppointmentDetailsActions({ appointment, onEdit, onCancel, onDelete }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');

    const cancelled = isCancelled(appointment);

    return (
        <div className="grid gap-3">
            <div className="flex flex-wrap justify-end gap-2">
                {cancelled ? null : (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onCancel}
                        className="h-11 px-4 md:h-9"
                    >
                        <CalendarX2 aria-hidden="true" />
                        {t('calendar.appointment.actions.cancel')}
                    </Button>
                )}

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
            </div>

            <div className="flex border-t border-border pt-3">
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
            </div>
        </div>
    );
}

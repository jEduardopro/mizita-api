import { NotepadText } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { fieldMessage } from '@/components/form/FieldMessage';
import { Textarea } from '@/components/ui/textarea';
import { AppointmentFormRow } from './AppointmentFormRow';
import type { AppointmentFormController } from './use-appointment-form';

const FIELD_ID = 'appointment-notes';

const NOTES_ROWS = 2;

type Props = {
    form: AppointmentFormController;
};

export function AppointmentNotesField({ form }: Props) {
    const { t } = useTranslation('admin');
    const error = form.errorFor('notes');

    const message = fieldMessage({
        id: FIELD_ID,
        error,
        hint: t('calendar.appointment.form.notes.hint'),
    });

    return (
        <AppointmentFormRow
            icon={<NotepadText />}
            label={t('calendar.appointment.form.notes.label')}
            htmlFor={FIELD_ID}
            message={message}
        >
            <Textarea
                id={FIELD_ID}
                rows={NOTES_ROWS}
                placeholder={t('calendar.appointment.form.notes.placeholder')}
                aria-invalid={!! error}
                aria-describedby={message?.id}
                value={form.values.notes}
                onChange={(event) => form.update('notes', event.target.value)}
            />
        </AppointmentFormRow>
    );
}

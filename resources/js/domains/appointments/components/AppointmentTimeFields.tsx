import { useTranslation } from 'react-i18next';
import { FieldRow } from '@/components/form/FieldRow';
import { FormField } from '@/components/form/FormField';
import { NumberField } from '@/components/form/NumberField';
import type { AppointmentFormController } from './use-appointment-form';

type Props = {
    form: AppointmentFormController;
};

export function AppointmentTimeFields({ form }: Props) {
    const { t } = useTranslation('admin');
    const { date, startsAt, endsAt, durationMinutes } = form.values;

    return (
        <div className="grid gap-5">
            <FormField
                id="appointment-date"
                type="date"
                label={t('calendar.appointment.form.date.label')}
                value={date}
                onChange={(event) => form.update('date', event.target.value)}
                error={form.errorFor('date')}
            />

            <FieldRow columns={3}>
                <FormField
                    id="appointment-starts-at"
                    type="time"
                    label={t('calendar.appointment.form.startsAt.label')}
                    placeholder={t('calendar.appointment.form.startsAt.placeholder')}
                    value={startsAt}
                    onChange={(event) => form.setStartsAt(event.target.value)}
                    error={form.errorFor('startsAt')}
                />

                <NumberField
                    id="appointment-duration"
                    label={t('calendar.appointment.form.duration.label')}
                    placeholder={t('calendar.appointment.form.duration.placeholder')}
                    suffix={t('calendar.appointment.form.duration.suffix')}
                    value={durationMinutes}
                    onChange={form.setDuration}
                    hint={t('calendar.appointment.form.duration.hint')}
                    error={form.errorFor('durationMinutes')}
                />

                <FormField
                    id="appointment-ends-at"
                    type="time"
                    label={t('calendar.appointment.form.endsAt.label')}
                    value={endsAt}
                    onChange={(event) => form.setEndsAt(event.target.value)}
                    hint={t('calendar.appointment.form.endsAt.hint')}
                    error={form.errorFor('endsAt')}
                />
            </FieldRow>
        </div>
    );
}

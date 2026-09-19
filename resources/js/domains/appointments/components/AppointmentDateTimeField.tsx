import { Clock } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { DatePicker } from '@/components/form/DatePicker';
import { fieldMessage } from '@/components/form/FieldMessage';
import { TimePicker } from '@/components/form/TimePicker';
import {
    APPOINTMENT_CONTROL_HEIGHT,
    AppointmentFormRow,
    appointmentLabelId,
} from './AppointmentFormRow';
import type { AppointmentFormController } from './use-appointment-form';

const DATE_FIELD_ID = 'appointment-date';

const STARTS_AT_FIELD_ID = 'appointment-starts-at';

const ENDS_AT_FIELD_ID = 'appointment-ends-at';

const DATE_PICKER_HEIGHT = 'h-11 md:h-9 md:text-sm';

const MINUTES_IN_HOUR = 60;

type Props = {
    form: AppointmentFormController;
};

export function AppointmentDateTimeField({ form }: Props) {
    const { t } = useTranslation('admin');
    const { date, startsAt, endsAt } = form.values;
    const dateError = form.errorFor('date');
    const startsAtError = form.errorFor('startsAt');
    const endsAtError = form.errorFor('endsAt');

    const message = fieldMessage({
        id: DATE_FIELD_ID,
        error: dateError ?? startsAtError ?? endsAtError,
    });

    function durationLabel(totalMinutes: number): string {
        const hours = Math.floor(totalMinutes / MINUTES_IN_HOUR);
        const minutes = totalMinutes % MINUTES_IN_HOUR;

        if (hours === 0) {
            return t('calendar.appointment.form.endsAt.duration.minutes', { minutes });
        }

        if (minutes === 0) {
            return t('calendar.appointment.form.endsAt.duration.hours', { hours });
        }

        return t('calendar.appointment.form.endsAt.duration.hoursMinutes', { hours, minutes });
    }

    return (
        <AppointmentFormRow
            icon={<Clock />}
            label={t('calendar.appointment.form.dateTime.label')}
            htmlFor={DATE_FIELD_ID}
            message={message}
        >
            <div className="flex flex-wrap items-center gap-2">
                <div className="w-full sm:w-40">
                    <DatePicker
                        id={DATE_FIELD_ID}
                        labelledBy={`${appointmentLabelId(DATE_FIELD_ID)} ${DATE_FIELD_ID}`}
                        value={date}
                        onChange={(value) => form.update('date', value)}
                        format={{
                            day: t('calendar.appointment.form.dateTime.format.day'),
                            month: t('calendar.appointment.form.dateTime.format.month'),
                            year: t('calendar.appointment.form.dateTime.format.year'),
                        }}
                        messages={{
                            calendar: t('calendar.appointment.form.dateTime.picker.calendar'),
                            previousMonth: t('calendar.appointment.form.dateTime.picker.previousMonth'),
                            nextMonth: t('calendar.appointment.form.dateTime.picker.nextMonth'),
                            month: t('calendar.appointment.form.dateTime.picker.month'),
                            year: t('calendar.appointment.form.dateTime.picker.year'),
                            today: t('calendar.appointment.form.dateTime.picker.today'),
                            clear: t('calendar.appointment.form.dateTime.picker.clear'),
                        }}
                        invalid={!! dateError}
                        describedBy={message?.id}
                        className={DATE_PICKER_HEIGHT}
                    />
                </div>

                <div className="min-w-0 flex-1">
                    <TimePicker
                        id={STARTS_AT_FIELD_ID}
                        label={t('calendar.appointment.form.startsAt.label')}
                        value={startsAt}
                        onChange={form.setStartsAt}
                        messages={{
                            list: t('calendar.appointment.form.startsAt.list'),
                            empty: t('calendar.appointment.form.startsAt.empty'),
                        }}
                        invalid={!! startsAtError}
                        describedBy={message?.id}
                        className={APPOINTMENT_CONTROL_HEIGHT}
                    />
                </div>

                <span aria-hidden="true" className="text-muted-foreground">
                    –
                </span>

                <div className="min-w-0 flex-1">
                    <TimePicker
                        id={ENDS_AT_FIELD_ID}
                        label={t('calendar.appointment.form.endsAt.label')}
                        value={endsAt}
                        onChange={form.setEndsAt}
                        messages={{
                            list: t('calendar.appointment.form.endsAt.list'),
                            empty: t('calendar.appointment.form.endsAt.empty'),
                        }}
                        startsFrom={startsAt === '' ? undefined : startsAt}
                        durationLabel={durationLabel}
                        invalid={!! endsAtError}
                        describedBy={message?.id}
                        className={APPOINTMENT_CONTROL_HEIGHT}
                    />
                </div>
            </div>
        </AppointmentFormRow>
    );
}

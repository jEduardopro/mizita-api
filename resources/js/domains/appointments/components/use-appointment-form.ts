import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import 'temporal-polyfill/global';
import type { NumberValue } from '@/components/form/NumberField';
import { useErrorToast } from '@/hooks/use-error-toast';
import { errorCodeFrom, fieldErrorsFrom, formMessageFrom, type FieldErrors } from '@/lib/http';
import { todayAsIsoDate } from '@/lib/time';
import { raiseSuccessToast } from '@/lib/toast';
import { useCreateAppointment, useUpdateAppointment } from '../queries';
import type { Appointment, AppointmentPayload, AppointmentService } from '../types';

const OVERLAP_ERROR_CODE = 'appointment_overlap';

const MINUTES_PER_DAY = 24 * 60;

export type AppointmentFormMode = 'create' | 'edit';

type SelectedCustomer = { id: string; name: string };

export type AppointmentFormValues = {
    service: AppointmentService | null;
    customer: SelectedCustomer | null;
    staffMemberId: string;
    date: string;
    startsAt: string;
    endsAt: string;
    durationMinutes: NumberValue;
    notes: string;
};

type AppointmentField = keyof AppointmentFormValues;

const SERVER_FIELDS: Record<AppointmentField, string> = {
    service: 'service_id',
    customer: 'customer_id',
    staffMemberId: 'staff_member_id',
    date: 'starts_at',
    startsAt: 'starts_at',
    endsAt: 'ends_at',
    durationMinutes: 'starts_at',
    notes: 'notes',
};

export type AppointmentFormController = {
    values: AppointmentFormValues;
    update: <TKey extends AppointmentField>(key: TKey, value: AppointmentFormValues[TKey]) => void;
    setService: (service: AppointmentService | null) => void;
    setStartsAt: (time: string) => void;
    setEndsAt: (time: string) => void;
    setDuration: (value: NumberValue) => void;
    errorFor: (field: AppointmentField) => string | undefined;
    isSubmitting: boolean;
    submit: () => Promise<boolean>;
};

type Params = {
    mode: AppointmentFormMode;
    appointment: Appointment | null;
    timezone: string;
    prefillStartsAt: string | null;
    onSaved: (appointment: Appointment) => void;
};

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

function minutesSinceMidnight(time: string): number {
    const [hourText, minuteText] = time.split(':');

    return Number(hourText) * 60 + Number(minuteText);
}

function timeFromMinutes(totalMinutes: number): string {
    const clamped = ((totalMinutes % MINUTES_PER_DAY) + MINUTES_PER_DAY) % MINUTES_PER_DAY;

    return `${pad(Math.floor(clamped / 60))}:${pad(clamped % 60)}`;
}

function toLocalDateAndTime(instant: string, timezone: string): { date: string; time: string } {
    const zoned = Temporal.Instant.from(instant).toZonedDateTimeISO(timezone);

    return {
        date: zoned.toPlainDate().toString(),
        time: zoned.toPlainTime().toString({ smallestUnit: 'minute' }),
    };
}

function toInstant(date: string, time: string, timezone: string): string {
    return Temporal.PlainDate.from(date)
        .toZonedDateTime({ timeZone: timezone, plainTime: Temporal.PlainTime.from(time) })
        .toInstant()
        .toString();
}

function initialValues(
    appointment: Appointment | null,
    timezone: string,
    prefillStartsAt: string | null,
): AppointmentFormValues {
    if (appointment !== null) {
        const starts = toLocalDateAndTime(appointment.starts_at, timezone);
        const ends = toLocalDateAndTime(appointment.ends_at, timezone);

        return {
            service: appointment.service,
            customer: { id: appointment.customer.id, name: appointment.customer.name },
            staffMemberId: appointment.staff_member.id,
            date: starts.date,
            startsAt: starts.time,
            endsAt: ends.time,
            durationMinutes: appointment.duration_minutes,
            notes: appointment.notes ?? '',
        };
    }

    if (prefillStartsAt !== null) {
        const starts = toLocalDateAndTime(prefillStartsAt, timezone);

        return {
            service: null,
            customer: null,
            staffMemberId: '',
            date: starts.date,
            startsAt: starts.time,
            endsAt: '',
            durationMinutes: '',
            notes: '',
        };
    }

    return {
        service: null,
        customer: null,
        staffMemberId: '',
        date: todayAsIsoDate(),
        startsAt: '',
        endsAt: '',
        durationMinutes: '',
        notes: '',
    };
}

function payloadFrom(values: AppointmentFormValues, timezone: string): AppointmentPayload {
    const notes = values.notes.trim();

    return {
        customer_id: values.customer?.id ?? '',
        service_id: values.service?.id ?? '',
        staff_member_id: values.staffMemberId,
        starts_at: toInstant(values.date, values.startsAt, timezone),
        ends_at: values.endsAt === '' ? null : toInstant(values.date, values.endsAt, timezone),
        notes: notes === '' ? null : notes,
    };
}

export function useAppointmentForm({
    mode,
    appointment,
    timezone,
    prefillStartsAt,
    onSaved,
}: Params): AppointmentFormController {
    const { t } = useTranslation('admin');
    const [values, setValues] = useState(() => initialValues(appointment, timezone, prefillStartsAt));
    const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
    const errorToast = useErrorToast();
    const createAppointment = useCreateAppointment();
    const updateAppointment = useUpdateAppointment();

    const clearField = useCallback((name: string) => {
        setFieldErrors((current) => {
            if (! (name in current)) {
                return current;
            }

            const remaining: FieldErrors = {};

            for (const field of Object.keys(current)) {
                if (field !== name) {
                    remaining[field] = current[field];
                }
            }

            return remaining;
        });
    }, []);

    const update = useCallback(
        <TKey extends AppointmentField>(key: TKey, value: AppointmentFormValues[TKey]) => {
            setValues((current) => ({ ...current, [key]: value }));
            clearField(SERVER_FIELDS[key]);
        },
        [clearField],
    );

    const setService = useCallback(
        (service: AppointmentService | null) => {
            setValues((current) => {
                if (service === null || current.startsAt === '') {
                    return { ...current, service, durationMinutes: service?.duration_minutes ?? '' };
                }

                return {
                    ...current,
                    service,
                    durationMinutes: service.duration_minutes,
                    endsAt: timeFromMinutes(
                        minutesSinceMidnight(current.startsAt) + service.duration_minutes,
                    ),
                };
            });
            clearField(SERVER_FIELDS.service);
        },
        [clearField],
    );

    const setStartsAt = useCallback(
        (time: string) => {
            setValues((current) => {
                if (time === '' || current.durationMinutes === '') {
                    return { ...current, startsAt: time };
                }

                return {
                    ...current,
                    startsAt: time,
                    endsAt: timeFromMinutes(minutesSinceMidnight(time) + current.durationMinutes),
                };
            });
            clearField(SERVER_FIELDS.startsAt);
        },
        [clearField],
    );

    const setEndsAt = useCallback(
        (time: string) => {
            setValues((current) => {
                if (time === '' || current.startsAt === '') {
                    return { ...current, endsAt: time };
                }

                return {
                    ...current,
                    endsAt: time,
                    durationMinutes: Math.max(
                        0,
                        minutesSinceMidnight(time) - minutesSinceMidnight(current.startsAt),
                    ),
                };
            });
            clearField(SERVER_FIELDS.endsAt);
        },
        [clearField],
    );

    const setDuration = useCallback(
        (value: NumberValue) => {
            setValues((current) => {
                if (value === '' || current.startsAt === '') {
                    return { ...current, durationMinutes: value };
                }

                return {
                    ...current,
                    durationMinutes: value,
                    endsAt: timeFromMinutes(minutesSinceMidnight(current.startsAt) + value),
                };
            });
            clearField(SERVER_FIELDS.durationMinutes);
        },
        [clearField],
    );

    const errorFor = useCallback(
        (field: AppointmentField) => fieldErrors[SERVER_FIELDS[field]],
        [fieldErrors],
    );

    function captureFailure(error: unknown) {
        setFieldErrors(fieldErrorsFrom(error));

        const message =
            errorCodeFrom(error) === OVERLAP_ERROR_CODE
                ? t('calendar.appointment.errors.overlap')
                : formMessageFrom(error, t('calendar.appointment.errors.saveFailed'));

        errorToast.show(message);
    }

    async function submit(): Promise<boolean> {
        setFieldErrors({});
        errorToast.dismiss();

        try {
            const payload = payloadFrom(values, timezone);

            const saved =
                mode === 'edit' && appointment !== null
                    ? await updateAppointment.mutateAsync({ id: appointment.id, payload })
                    : await createAppointment.mutateAsync(payload);

            raiseSuccessToast(
                mode === 'edit'
                    ? t('calendar.appointment.toasts.updated')
                    : t('calendar.appointment.toasts.created'),
            );
            onSaved(saved);

            return true;
        } catch (error) {
            captureFailure(error);

            return false;
        }
    }

    return {
        values,
        update,
        setService,
        setStartsAt,
        setEndsAt,
        setDuration,
        errorFor,
        isSubmitting: createAppointment.isPending || updateAppointment.isPending,
        submit,
    };
}

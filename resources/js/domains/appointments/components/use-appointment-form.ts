import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useErrorToast } from '@/hooks/use-error-toast';
import { errorCodeFrom, fieldErrorsFrom, formMessageFrom, type FieldErrors } from '@/lib/http';
import { raiseSuccessToast } from '@/lib/toast';
import {
    appointmentPayloadFrom,
    initialAppointmentValues,
    missingRequiredFields,
    serverFields,
    withEndsAt,
    withService,
    withStartsAt,
    type AppointmentChange,
    type AppointmentField,
    type AppointmentFormValues,
    type RequiredAppointmentField,
} from './appointment-form-values';
import { useCreateAppointment, useUpdateAppointment } from '../queries';
import type { Appointment, AppointmentService } from '../types';

const OVERLAP_ERROR_CODE = 'appointment_overlap';

const REQUIRED_MESSAGE_KEYS = {
    service: 'calendar.appointment.form.service.required',
    customer: 'calendar.appointment.form.customer.required',
    date: 'calendar.appointment.form.dateTime.required',
    startsAt: 'calendar.appointment.form.startsAt.required',
    endsAt: 'calendar.appointment.form.endsAt.required',
} as const satisfies Record<RequiredAppointmentField, string>;

type RequiredErrors = Partial<Record<AppointmentField, string>>;

export type AppointmentFormMode = 'create' | 'edit';

export type AppointmentFormController = {
    values: AppointmentFormValues;
    update: <TKey extends AppointmentField>(key: TKey, value: AppointmentFormValues[TKey]) => void;
    setService: (service: AppointmentService | null) => void;
    setStartsAt: (time: string) => void;
    setEndsAt: (time: string) => void;
    errorFor: (field: AppointmentField) => string | undefined;
    isSubmitting: boolean;
    submit: () => Promise<boolean>;
};

export type AppointmentFormParams = {
    mode: AppointmentFormMode;
    appointment: Appointment | null;
    timezone: string;
    prefillStartsAt: string | null;
    onSaved: (appointment: Appointment) => void;
};

export function useAppointmentForm({
    mode,
    appointment,
    timezone,
    prefillStartsAt,
    onSaved,
}: AppointmentFormParams): AppointmentFormController {
    const { t } = useTranslation('admin');
    const [values, setValues] = useState(() =>
        initialAppointmentValues(appointment, timezone, prefillStartsAt),
    );
    const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
    const [requiredErrors, setRequiredErrors] = useState<RequiredErrors>({});
    const errorToast = useErrorToast();
    const createAppointment = useCreateAppointment();
    const updateAppointment = useUpdateAppointment();

    const clearServerError = useCallback((name: string) => {
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
            clearServerError(serverFields[key]);
        },
        [clearServerError],
    );

    function applyChange(change: AppointmentChange) {
        setValues(change.values);

        for (const field of change.written) {
            clearServerError(serverFields[field]);
        }
    }

    function setService(service: AppointmentService | null) {
        applyChange(withService(values, service));
    }

    function setStartsAt(time: string) {
        applyChange(withStartsAt(values, time));
    }

    function setEndsAt(time: string) {
        applyChange(withEndsAt(values, time));
    }

    const stillMissing = new Set<AppointmentField>(missingRequiredFields(values));

    function requiredErrorFor(field: AppointmentField): string | undefined {
        return stillMissing.has(field) ? requiredErrors[field] : undefined;
    }

    function errorFor(field: AppointmentField): string | undefined {
        return requiredErrorFor(field) ?? fieldErrors[serverFields[field]];
    }

    function requiredMessagesFor(fields: RequiredAppointmentField[]): RequiredErrors {
        const messages: RequiredErrors = {};

        for (const field of fields) {
            messages[field] = t(REQUIRED_MESSAGE_KEYS[field]);
        }

        return messages;
    }

    function captureFailure(error: unknown) {
        const serverErrors = fieldErrorsFrom(error);

        setFieldErrors(serverErrors);

        if (Object.keys(serverErrors).length > 0) {
            return;
        }

        errorToast.show(
            errorCodeFrom(error) === OVERLAP_ERROR_CODE
                ? t('calendar.appointment.errors.overlap')
                : formMessageFrom(error, t('calendar.appointment.errors.saveFailed')),
        );
    }

    async function submit(): Promise<boolean> {
        setFieldErrors({});
        errorToast.dismiss();

        const missing = missingRequiredFields(values);

        if (missing.length > 0) {
            setRequiredErrors(requiredMessagesFor(missing));

            return false;
        }

        setRequiredErrors({});

        try {
            const payload = appointmentPayloadFrom(values, timezone);

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
        errorFor,
        isSubmitting: createAppointment.isPending || updateAppointment.isPending,
        submit,
    };
}

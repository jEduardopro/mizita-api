import { useCallback, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { matchesServerField } from '@/domains/businesses/components/server-field-path';
import { useBusinessSettings, useUpdateBusinessSettings } from '@/domains/businesses/queries';
import type { ContactFieldLevel, ContactFieldName } from '@/domains/businesses/types';
import { useServerErrors } from '@/hooks/use-server-errors';
import { raiseSuccessToast } from '@/lib/toast';
import {
    bookingPreferencesPayloadFrom,
    contactFieldServerKey,
    initialBookingPreferencesValues,
    serverFields,
    type BookingPreferencesField,
    type BookingPreferencesFormValues,
} from './booking-preferences-values';

export const BOOKING_PREFERENCES_FORM_ID = 'booking-preferences-form';

export type BookingPreferencesFormController = {
    values: BookingPreferencesFormValues;
    update: <TKey extends BookingPreferencesField>(
        key: TKey,
        value: BookingPreferencesFormValues[TKey],
    ) => void;
    updateContactField: (name: ContactFieldName, level: ContactFieldLevel) => void;
    errorFor: (field: BookingPreferencesField) => string | undefined;
    contactFieldErrorFor: (name: ContactFieldName) => string | undefined;
    isLoading: boolean;
    isLoadError: boolean;
    retry: () => void;
    isSubmitting: boolean;
    submit: (event: FormEvent<HTMLFormElement>) => void;
};

export function useBookingPreferencesForm(): BookingPreferencesFormController {
    const { t } = useTranslation('admin');
    const { fieldErrors, capture, clearField, reset } = useServerErrors();

    const settingsQuery = useBusinessSettings();
    const settings = settingsQuery.data ?? null;

    const [values, setValues] = useState(() => initialBookingPreferencesValues(settings));
    const [loadedSettingsId, setLoadedSettingsId] = useState(settings?.id ?? null);

    if ((settings?.id ?? null) !== loadedSettingsId) {
        setLoadedSettingsId(settings?.id ?? null);
        setValues(initialBookingPreferencesValues(settings));
    }

    const updateSettings = useUpdateBusinessSettings();

    const clearErrorsAt = useCallback(
        (serverField: string) => {
            for (const field of Object.keys(fieldErrors)) {
                if (matchesServerField(field, serverField)) {
                    clearField(field);
                }
            }
        },
        [fieldErrors, clearField],
    );

    const errorAt = useCallback(
        (serverField: string) => {
            const key = Object.keys(fieldErrors).find((candidate) =>
                matchesServerField(candidate, serverField),
            );

            return key === undefined ? undefined : fieldErrors[key];
        },
        [fieldErrors],
    );

    const update = useCallback(
        <TKey extends BookingPreferencesField>(
            key: TKey,
            value: BookingPreferencesFormValues[TKey],
        ) => {
            setValues((current) => ({ ...current, [key]: value }));
            clearErrorsAt(serverFields[key]);
        },
        [clearErrorsAt],
    );

    const updateContactField = useCallback(
        (name: ContactFieldName, level: ContactFieldLevel) => {
            setValues((current) => ({
                ...current,
                contactFields: { ...current.contactFields, [name]: level },
            }));
            clearErrorsAt(contactFieldServerKey(name));
        },
        [clearErrorsAt],
    );

    const errorFor = useCallback(
        (field: BookingPreferencesField) => errorAt(serverFields[field]),
        [errorAt],
    );

    const contactFieldErrorFor = useCallback(
        (name: ContactFieldName) => errorAt(contactFieldServerKey(name)),
        [errorAt],
    );

    const retry = useCallback(() => void settingsQuery.refetch(), [settingsQuery]);

    async function save() {
        reset();

        try {
            await updateSettings.mutateAsync(bookingPreferencesPayloadFrom(values));
        } catch (error) {
            capture(error, t('bookingPreferences.errors.unexpected'));

            return;
        }

        raiseSuccessToast(t('bookingPreferences.toasts.saved'));
    }

    return {
        values,
        update,
        updateContactField,
        errorFor,
        contactFieldErrorFor,
        isLoading: settingsQuery.isPending,
        isLoadError: settingsQuery.isError,
        retry,
        isSubmitting: updateSettings.isPending,
        submit: (event: FormEvent<HTMLFormElement>) => {
            event.preventDefault();
            void save();
        },
    };
}

import { useMemo, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import type { DialCodeOption } from '@/components/form/DialCodePicker';
import { useServerErrors } from '@/hooks/use-server-errors';
import { isRateLimitedError } from '@/lib/http';
import type { PublicContactFields, PublicGuestPayload } from '../../types';
import {
    BOOKING_DETAILS_SERVER_FIELDS,
    EMPTY_BOOKING_DETAILS,
    guestFrom,
    missingRequiredFields,
    notesFrom,
    phoneCountryOptions,
    supportedPhoneCountry,
    type BookingDetailsField,
    type BookingDetailsValues,
} from './booking-details-values';

type Options = {
    contactFields: PublicContactFields;
    onSubmit(guest: PublicGuestPayload, notes: string | null): Promise<void>;
};

export type BookingDetailsFormController = {
    values: BookingDetailsValues;
    countries: DialCodeOption[];
    update<Field extends BookingDetailsField>(field: Field, value: BookingDetailsValues[Field]): void;
    selectPhoneCountry(code: string): void;
    errorFor(field: BookingDetailsField): string | undefined;
    handleSubmit(event: FormEvent<HTMLFormElement>): Promise<void>;
};

export function useBookingDetailsForm({ contactFields, onSubmit }: Options): BookingDetailsFormController {
    const { t, i18n } = useTranslation('public');
    const [values, setValues] = useState<BookingDetailsValues>(EMPTY_BOOKING_DETAILS);
    const [attempted, setAttempted] = useState(false);
    const serverErrors = useServerErrors();

    const countries = useMemo(() => phoneCountryOptions(i18n.language), [i18n.language]);
    const missingFields = attempted ? missingRequiredFields(values, contactFields) : [];

    function update<Field extends BookingDetailsField>(
        field: Field,
        value: BookingDetailsValues[Field],
    ): void {
        setValues((current) => ({ ...current, [field]: value }));
        serverErrors.clearField(BOOKING_DETAILS_SERVER_FIELDS[field]);
    }

    function selectPhoneCountry(code: string): void {
        const country = supportedPhoneCountry(code);

        if (country !== null) {
            update('phoneCountry', country);
        }
    }

    function errorFor(field: BookingDetailsField): string | undefined {
        if (missingFields.includes(field)) {
            return t('booking.flow.details.required');
        }

        return serverErrors.fieldErrors[BOOKING_DETAILS_SERVER_FIELDS[field]];
    }

    function fallbackMessage(error: unknown): string {
        return isRateLimitedError(error)
            ? t('booking.flow.errors.tooMany')
            : t('booking.flow.errors.booking');
    }

    async function handleSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        setAttempted(true);

        if (missingRequiredFields(values, contactFields).length > 0) {
            return;
        }

        serverErrors.reset();

        try {
            await onSubmit(guestFrom(values, contactFields), notesFrom(values));
        } catch (error) {
            serverErrors.capture(error, fallbackMessage(error));
        }
    }

    return { values, countries, update, selectPhoneCountry, errorFor, handleSubmit };
}

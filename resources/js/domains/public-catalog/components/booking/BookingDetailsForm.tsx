import { cn } from 'cn';
import { useMemo, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField } from '@/components/form/FormField';
import { PhoneField } from '@/components/form/PhoneField';
import { SubmitButton } from '@/components/form/SubmitButton';
import { TextareaField } from '@/components/form/TextareaField';
import { useServerErrors } from '@/hooks/use-server-errors';
import {
    brandColorClasses,
    BUTTON_SHAPE_CLASSES,
    type BrandColor,
    type ButtonShape,
} from '@/lib/booking-brand';
import { isRateLimitedError } from '@/lib/http';
import type { PublicGuestPayload } from '../../types';
import {
    BOOKING_DETAILS_SERVER_FIELDS,
    EMPTY_BOOKING_DETAILS,
    guestFrom,
    hasContactDetails,
    notesFrom,
    phoneCountryOptions,
    supportedPhoneCountry,
    type BookingDetailsField,
    type BookingDetailsValues,
} from './booking-details-values';

type Props = {
    accentColor: BrandColor;
    buttonShape: ButtonShape;
    isSubmitting: boolean;
    onSubmit(guest: PublicGuestPayload, notes: string | null): Promise<void>;
};

export function BookingDetailsForm({ accentColor, buttonShape, isSubmitting, onSubmit }: Props) {
    const { t, i18n } = useTranslation('public');
    const [values, setValues] = useState<BookingDetailsValues>(EMPTY_BOOKING_DETAILS);
    const [attempted, setAttempted] = useState(false);
    const serverErrors = useServerErrors();

    const countries = useMemo(() => phoneCountryOptions(i18n.language), [i18n.language]);
    const accent = brandColorClasses[accentColor];
    const contactMissing = attempted && ! hasContactDetails(values);

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

        if (! hasContactDetails(values)) {
            return;
        }

        serverErrors.reset();

        try {
            await onSubmit(guestFrom(values), notesFrom(values));
        } catch (error) {
            serverErrors.capture(error, fallbackMessage(error));
        }
    }

    return (
        <form onSubmit={handleSubmit} className="grid gap-6">
            <div className="grid gap-5 rounded-2xl border border-border bg-card p-5 text-card-foreground shadow-sm sm:p-6">
                <FormField
                    id="booking-guest-name"
                    label={t('booking.flow.details.name.label')}
                    placeholder={t('booking.flow.details.name.placeholder')}
                    autoComplete="name"
                    required
                    value={values.name}
                    onChange={(event) => update('name', event.target.value)}
                    error={errorFor('name')}
                />

                <PhoneField
                    id="booking-guest-phone"
                    label={t('booking.flow.details.phone.label')}
                    countryLabel={t('booking.flow.details.phone.label')}
                    numberLabel={t('booking.flow.details.phone.label')}
                    countries={countries}
                    country={values.phoneCountry}
                    onCountryChange={selectPhoneCountry}
                    number={values.phoneNumber}
                    onNumberChange={(value) => update('phoneNumber', value)}
                    hint={t('booking.flow.details.phone.hint')}
                    error={
                        contactMissing
                            ? t('booking.flow.details.contactHint')
                            : errorFor('phoneNumber')
                    }
                />

                <FormField
                    id="booking-guest-email"
                    type="email"
                    inputMode="email"
                    autoComplete="email"
                    autoCapitalize="none"
                    spellCheck={false}
                    label={t('booking.flow.details.email.label')}
                    hint={t('booking.flow.details.email.hint')}
                    value={values.email}
                    onChange={(event) => update('email', event.target.value)}
                    error={errorFor('email')}
                />

                <TextareaField
                    id="booking-notes"
                    label={t('booking.flow.details.notes.label')}
                    hint={t('booking.flow.details.notes.hint')}
                    value={values.notes}
                    onChange={(event) => update('notes', event.target.value)}
                    error={errorFor('notes')}
                />
            </div>

            <SubmitButton
                variant={null}
                size="xl"
                label={t('booking.flow.details.submit')}
                submittingLabel={t('booking.flow.details.submitting')}
                isSubmitting={isSubmitting}
                className={cn(
                    'w-full hover:opacity-90 sm:w-auto sm:min-w-64 sm:justify-self-start',
                    accent.accent,
                    accent.accentForeground,
                    BUTTON_SHAPE_CLASSES[buttonShape],
                )}
            />
        </form>
    );
}

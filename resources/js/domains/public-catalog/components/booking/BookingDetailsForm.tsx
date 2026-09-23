import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import { FormField } from '@/components/form/FormField';
import { PhoneField } from '@/components/form/PhoneField';
import { SubmitButton } from '@/components/form/SubmitButton';
import { TextareaField } from '@/components/form/TextareaField';
import {
    brandColorClasses,
    BUTTON_SHAPE_CLASSES,
    type BrandColor,
    type ButtonShape,
} from '@/lib/booking-brand';
import type { PublicContactFields, PublicGuestPayload } from '../../types';
import { BookingAddressFields } from './BookingAddressFields';
import {
    isContactFieldOptional,
    isContactFieldRequired,
    isContactFieldShown,
    type ShownContactFieldLevel,
} from './contact-field-levels';
import { ContactFieldLabel } from './ContactFieldLabel';
import { useBookingDetailsForm } from './use-booking-details-form';

type Props = {
    accentColor: BrandColor;
    buttonShape: ButtonShape;
    contactFields: PublicContactFields;
    isSubmitting: boolean;
    onSubmit(guest: PublicGuestPayload, notes: string | null): Promise<void>;
};

export function BookingDetailsForm({
    accentColor,
    buttonShape,
    contactFields,
    isSubmitting,
    onSubmit,
}: Props) {
    const { t } = useTranslation('public');
    const form = useBookingDetailsForm({ contactFields, onSubmit });
    const accent = brandColorClasses[accentColor];
    const { phone, email, address } = contactFields;

    function hintFor(level: ShownContactFieldLevel, hint: string): string {
        return isContactFieldOptional(level) ? t('booking.flow.details.optionalHint', { hint }) : hint;
    }

    return (
        <form onSubmit={form.handleSubmit} className="grid gap-6">
            <div className="grid gap-5 rounded-2xl border border-border bg-card p-5 text-card-foreground shadow-sm sm:p-6">
                <FormField
                    id="booking-guest-name"
                    label={<ContactFieldLabel text={t('booking.flow.details.name.label')} required />}
                    placeholder={t('booking.flow.details.name.placeholder')}
                    autoComplete="name"
                    required
                    value={form.values.name}
                    onChange={(event) => form.update('name', event.target.value)}
                    error={form.errorFor('name')}
                />

                {isContactFieldShown(phone) ? (
                    <PhoneField
                        id="booking-guest-phone"
                        label={
                            <ContactFieldLabel
                                text={t('booking.flow.details.phone.label')}
                                required={isContactFieldRequired(phone)}
                            />
                        }
                        countryLabel={t('booking.flow.details.phone.country')}
                        numberLabel={t('booking.flow.details.phone.number')}
                        countries={form.countries}
                        country={form.values.phoneCountry}
                        onCountryChange={form.selectPhoneCountry}
                        number={form.values.phoneNumber}
                        onNumberChange={(value) => form.update('phoneNumber', value)}
                        required={isContactFieldRequired(phone)}
                        hint={hintFor(phone, t('booking.flow.details.phone.hint'))}
                        error={form.errorFor('phoneNumber')}
                    />
                ) : null}

                {isContactFieldShown(email) ? (
                    <FormField
                        id="booking-guest-email"
                        type="email"
                        inputMode="email"
                        autoComplete="email"
                        autoCapitalize="none"
                        spellCheck={false}
                        label={
                            <ContactFieldLabel
                                text={t('booking.flow.details.email.label')}
                                required={isContactFieldRequired(email)}
                            />
                        }
                        required={isContactFieldRequired(email)}
                        hint={hintFor(email, t('booking.flow.details.email.hint'))}
                        value={form.values.email}
                        onChange={(event) => form.update('email', event.target.value)}
                        error={form.errorFor('email')}
                    />
                ) : null}

                {isContactFieldShown(address) ? (
                    <BookingAddressFields
                        level={address}
                        values={form.values}
                        onChange={form.update}
                        errorFor={form.errorFor}
                    />
                ) : null}

                <TextareaField
                    id="booking-notes"
                    label={t('booking.flow.details.notes.label')}
                    hint={t('booking.flow.details.notes.hint')}
                    value={form.values.notes}
                    onChange={(event) => form.update('notes', event.target.value)}
                    error={form.errorFor('notes')}
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

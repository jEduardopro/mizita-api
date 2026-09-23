import { useTranslation } from 'react-i18next';
import { fieldMessage, FieldMessage } from '@/components/form/FieldMessage';
import { FieldRow } from '@/components/form/FieldRow';
import { FormField } from '@/components/form/FormField';
import type { BookingAddressField, BookingAddressValues } from './booking-details-values';
import {
    isContactFieldOptional,
    isContactFieldRequired,
    type ShownContactFieldLevel,
} from './contact-field-levels';
import { ContactFieldLabel } from './ContactFieldLabel';

const GROUP_ID = 'booking-guest-address';

type Props = {
    level: ShownContactFieldLevel;
    values: BookingAddressValues;
    onChange(field: BookingAddressField, value: string): void;
    errorFor(field: BookingAddressField): string | undefined;
};

export function BookingAddressFields({ level, values, onChange, errorFor }: Props) {
    const { t } = useTranslation('public');
    const required = isContactFieldRequired(level);
    const titleId = `${GROUP_ID}-title`;
    const message = fieldMessage({
        id: GROUP_ID,
        hint: isContactFieldOptional(level) ? t('booking.flow.details.optional') : undefined,
    });

    return (
        <div
            role="group"
            aria-labelledby={titleId}
            aria-describedby={message?.id}
            className="grid gap-4 border-t border-border pt-5"
        >
            <div className="grid gap-1">
                <span id={titleId} className="text-sm font-medium">
                    <ContactFieldLabel
                        text={t('booking.flow.details.address.label')}
                        required={required}
                    />
                </span>
                <FieldMessage message={message} />
            </div>

            <FormField
                id={`${GROUP_ID}-street`}
                label={t('booking.flow.details.address.street.label')}
                placeholder={t('booking.flow.details.address.street.placeholder')}
                autoComplete="address-line1"
                required={required}
                value={values.street}
                onChange={(event) => onChange('street', event.target.value)}
                error={errorFor('street')}
            />

            <FieldRow columns={3}>
                <FormField
                    id={`${GROUP_ID}-city`}
                    label={t('booking.flow.details.address.city.label')}
                    autoComplete="address-level2"
                    required={required}
                    value={values.city}
                    onChange={(event) => onChange('city', event.target.value)}
                    error={errorFor('city')}
                />

                <FormField
                    id={`${GROUP_ID}-state`}
                    label={t('booking.flow.details.address.state.label')}
                    autoComplete="address-level1"
                    required={required}
                    value={values.state}
                    onChange={(event) => onChange('state', event.target.value)}
                    error={errorFor('state')}
                />

                <FormField
                    id={`${GROUP_ID}-postal-code`}
                    label={t('booking.flow.details.address.postalCode.label')}
                    inputMode="numeric"
                    autoComplete="postal-code"
                    required={required}
                    value={values.postalCode}
                    onChange={(event) => onChange('postalCode', event.target.value)}
                    error={errorFor('postalCode')}
                />
            </FieldRow>
        </div>
    );
}

import { useTranslation } from 'react-i18next';
import { SettingsSection } from '@/domains/businesses/components/settings/SettingsSection';
import { BOOKING_PREFERENCES_SECTION_IDS } from './booking-preferences-values';
import { CONTACT_FIELD_LABEL_KEYS, CONTACT_FIELD_NAMES } from './contact-field-levels';
import { AlwaysCollectedFieldRow, ContactFieldRow } from './ContactFieldRows';
import type { BookingPreferencesFormController } from './use-booking-preferences-form';

const FIELD_ID_PREFIX = 'contact-field';

type Props = {
    form: BookingPreferencesFormController;
};

export function ContactFieldsSection({ form }: Props) {
    const { t } = useTranslation('admin');

    return (
        <SettingsSection
            id={BOOKING_PREFERENCES_SECTION_IDS.contactFields}
            title={t('bookingPreferences.contactFields.title')}
            description={t('bookingPreferences.contactFields.description')}
        >
            <ul className="-my-1.5 divide-y divide-border">
                <AlwaysCollectedFieldRow
                    id={`${FIELD_ID_PREFIX}-name`}
                    label={t('bookingPreferences.contactFields.fields.name')}
                />

                {CONTACT_FIELD_NAMES.map((name) => (
                    <ContactFieldRow
                        key={name}
                        id={`${FIELD_ID_PREFIX}-${name}`}
                        label={t(CONTACT_FIELD_LABEL_KEYS[name])}
                        level={form.values.contactFields[name]}
                        error={form.contactFieldErrorFor(name)}
                        onLevelChange={(level) => form.updateContactField(name, level)}
                    />
                ))}
            </ul>
        </SettingsSection>
    );
}

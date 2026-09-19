import { useTranslation } from 'react-i18next';
import { FormField } from '@/components/form/FormField';
import { PhoneField } from '@/components/form/PhoneField';
import { SUPPORTED_PHONE_COUNTRIES, type PhoneCountryCode } from '@/lib/phone';
import { BUSINESS_SETTINGS_SECTION_IDS } from './business-settings-values';
import { SettingsSection } from './SettingsSection';
import type { BusinessSettingsFormController } from './use-business-settings-form';

const COUNTRY_NAME_KEYS = {
    MX: 'businessSettings.contact.phone.countries.MX',
    US: 'businessSettings.contact.phone.countries.US',
} as const satisfies Record<PhoneCountryCode, string>;

type Props = {
    form: BusinessSettingsFormController;
};

export function ContactSection({ form }: Props) {
    const { t } = useTranslation('admin');

    function selectCountry(code: string): void {
        const country = SUPPORTED_PHONE_COUNTRIES.find((supported) => supported.code === code);

        if (country !== undefined) {
            form.update('phoneCountry', country.code);
        }
    }

    return (
        <SettingsSection
            id={BUSINESS_SETTINGS_SECTION_IDS.contact}
            title={t('businessSettings.contact.title')}
            description={t('businessSettings.contact.description')}
        >
            <FormField
                id="business-contact-email"
                type="email"
                inputMode="email"
                label={t('businessSettings.contact.email.label')}
                placeholder={t('businessSettings.contact.email.placeholder')}
                autoComplete="email"
                autoCapitalize="none"
                spellCheck={false}
                value={form.values.contactEmail}
                onChange={(event) => form.update('contactEmail', event.target.value)}
                hint={t('businessSettings.contact.email.hint')}
                error={form.errorFor('contactEmail')}
            />

            <PhoneField
                id="business-phone"
                label={t('businessSettings.contact.phone.label')}
                countryLabel={t('businessSettings.contact.phone.country')}
                numberLabel={t('businessSettings.contact.phone.number')}
                countries={SUPPORTED_PHONE_COUNTRIES.map((country) => ({
                    code: country.code,
                    name: t(COUNTRY_NAME_KEYS[country.code]),
                    dialCode: country.dialCode,
                }))}
                country={form.values.phoneCountry}
                onCountryChange={selectCountry}
                number={form.values.phoneNumber}
                onNumberChange={(value) => form.update('phoneNumber', value)}
                hint={t('businessSettings.contact.phone.hint')}
                error={form.errorFor('phoneNumber')}
            />
        </SettingsSection>
    );
}

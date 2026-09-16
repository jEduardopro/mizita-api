import { useTranslation } from 'react-i18next';
import { ComboboxField, comboboxOptionsStatus } from '@/components/form/ComboboxField';
import { FieldRow } from '@/components/form/FieldRow';
import { FormField } from '@/components/form/FormField';
import { SelectField } from '@/components/form/SelectField';
import { useStateChoices } from '@/domains/addresses/queries';
import { BUSINESS_SETTINGS_SECTION_IDS } from './business-settings-values';
import {
    COUNTRY_CODES,
    COUNTRY_LABEL_KEYS,
    CURRENCY_CODES,
    CURRENCY_LABEL_KEYS,
    DEFAULT_COUNTRY_CODE,
} from './location-options';
import { SettingsSection } from './SettingsSection';
import type { BusinessSettingsFormController } from './use-business-settings-form';

type Props = {
    form: BusinessSettingsFormController;
};

export function LocationSection({ form }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');
    const states = useStateChoices(DEFAULT_COUNTRY_CODE);

    return (
        <SettingsSection
            id={BUSINESS_SETTINGS_SECTION_IDS.location}
            title={t('businessSettings.location.title')}
            description={t('businessSettings.location.description')}
        >
            <FormField
                id="business-street"
                label={t('businessSettings.location.street.label')}
                placeholder={t('businessSettings.location.street.placeholder')}
                autoComplete="street-address"
                value={form.values.street}
                onChange={(event) => form.update('street', event.target.value)}
                error={form.errorFor('street')}
            />

            <FieldRow columns={2}>
                <FormField
                    id="business-city"
                    label={t('businessSettings.location.city.label')}
                    autoComplete="address-level2"
                    value={form.values.city}
                    onChange={(event) => form.update('city', event.target.value)}
                    error={form.errorFor('city')}
                />

                <FormField
                    id="business-postal-code"
                    label={t('businessSettings.location.postalCode.label')}
                    inputMode="numeric"
                    autoComplete="postal-code"
                    value={form.values.postalCode}
                    onChange={(event) => form.update('postalCode', event.target.value)}
                    error={form.errorFor('postalCode')}
                />
            </FieldRow>

            <ComboboxField
                id="business-state"
                label={t('businessSettings.location.state.label')}
                placeholder={t('businessSettings.location.state.placeholder')}
                options={states.options}
                value={form.values.stateId}
                onChange={(value) => form.update('stateId', value)}
                optionsStatus={comboboxOptionsStatus(states.isPending, states.isError)}
                onRetryOptions={states.refetch}
                messages={{
                    empty: t('businessSettings.location.state.empty'),
                    optionsError: t('businessSettings.location.state.error'),
                    retry: tCommon('actions.tryAgain'),
                    results: states.isPending
                        ? t('businessSettings.location.state.loading')
                        : t('businessSettings.location.state.results', {
                              count: states.options.length,
                          }),
                }}
                error={form.errorFor('stateId')}
            />

            <FieldRow columns={2}>
                <SelectField
                    id="business-country"
                    label={t('businessSettings.location.country.label')}
                    autoComplete="country"
                    options={COUNTRY_CODES.map((code) => ({
                        value: code,
                        label: t(COUNTRY_LABEL_KEYS[code]),
                    }))}
                    value={form.values.countryCode}
                    onChange={(event) => form.update('countryCode', event.target.value)}
                    error={form.errorFor('countryCode')}
                />

                <SelectField
                    id="business-currency"
                    label={t('businessSettings.location.currency.label')}
                    options={CURRENCY_CODES.map((code) => ({
                        value: code,
                        label: t(CURRENCY_LABEL_KEYS[code]),
                    }))}
                    value={form.values.currencyCode}
                    onChange={(event) => form.update('currencyCode', event.target.value)}
                    hint={t('businessSettings.location.currency.hint')}
                    error={form.errorFor('currencyCode')}
                />
            </FieldRow>
        </SettingsSection>
    );
}

import { useTranslation } from 'react-i18next';
import { ComboboxField, comboboxOptionsStatus } from '@/components/form/ComboboxField';
import { FieldRow } from '@/components/form/FieldRow';
import { FormField } from '@/components/form/FormField';
import { SelectField } from '@/components/form/SelectField';
import { useStateChoices } from '@/domains/addresses/queries';
import {
    CUSTOMER_CITY_MAX_LENGTH,
    CUSTOMER_POSTAL_CODE_MAX_LENGTH,
    CUSTOMER_STREET_MAX_LENGTH,
} from '../types';
import { CUSTOMER_COUNTRY_CODE } from './customer-form-values';
import { CustomerFormSection } from './CustomerFormSection';
import type { CustomerFormController } from './use-customer-form';

type Props = {
    form: CustomerFormController;
};

export function CustomerAddressFields({ form }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');
    const states = useStateChoices(CUSTOMER_COUNTRY_CODE);

    return (
        <CustomerFormSection
            title={t('customers.form.address.title')}
            description={t('customers.form.address.description')}
        >
            <FormField
                id="customer-street"
                label={t('customers.form.address.street.label')}
                placeholder={t('customers.form.address.street.placeholder')}
                autoComplete="street-address"
                aria-required={form.isRequired('street')}
                maxLength={CUSTOMER_STREET_MAX_LENGTH}
                value={form.values.street}
                onChange={(event) => form.update('street', event.target.value)}
                error={form.errorFor('street')}
            />

            <FieldRow columns={2}>
                <FormField
                    id="customer-city"
                    label={t('customers.form.address.city.label')}
                    autoComplete="address-level2"
                    aria-required={form.isRequired('city')}
                    maxLength={CUSTOMER_CITY_MAX_LENGTH}
                    value={form.values.city}
                    onChange={(event) => form.update('city', event.target.value)}
                    error={form.errorFor('city')}
                />

                <FormField
                    id="customer-postal-code"
                    label={t('customers.form.address.postalCode.label')}
                    inputMode="numeric"
                    autoComplete="postal-code"
                    aria-required={form.isRequired('postalCode')}
                    maxLength={CUSTOMER_POSTAL_CODE_MAX_LENGTH}
                    value={form.values.postalCode}
                    onChange={(event) => form.update('postalCode', event.target.value)}
                    error={form.errorFor('postalCode')}
                />
            </FieldRow>

            <ComboboxField
                id="customer-state"
                label={t('customers.form.address.state.label')}
                placeholder={t('customers.form.address.state.placeholder')}
                options={states.options}
                value={form.values.stateId}
                onChange={(value) => form.update('stateId', value)}
                optionsStatus={comboboxOptionsStatus(states.isPending, states.isError)}
                onRetryOptions={states.refetch}
                messages={{
                    empty: t('customers.form.address.state.empty'),
                    optionsError: t('customers.form.address.state.error'),
                    retry: tCommon('actions.tryAgain'),
                    results: states.isPending
                        ? t('customers.form.address.state.loading')
                        : t('customers.form.address.state.results', {
                              count: states.options.length,
                          }),
                }}
                error={form.errorFor('stateId')}
            />

            <SelectField
                id="customer-country"
                label={t('customers.form.address.country.label')}
                autoComplete="country"
                disabled
                options={[
                    {
                        value: CUSTOMER_COUNTRY_CODE,
                        label: t('customers.form.address.countries.MX'),
                    },
                ]}
                value={CUSTOMER_COUNTRY_CODE}
                hint={t('customers.form.address.country.hint')}
            />
        </CustomerFormSection>
    );
}

import { useTranslation } from 'react-i18next';
import { DateField } from '@/components/form/DateField';
import { FormField } from '@/components/form/FormField';
import { PhoneField } from '@/components/form/PhoneField';
import { TextareaField } from '@/components/form/TextareaField';
import { useInitialFocus } from '@/hooks/use-initial-focus';
import { SUPPORTED_PHONE_COUNTRIES, type PhoneCountryCode } from '@/lib/phone';
import { todayAsIsoDate } from '@/lib/time';
import {
    CUSTOMER_EMAIL_MAX_LENGTH,
    CUSTOMER_NAME_MAX_LENGTH,
    CUSTOMER_NOTES_MAX_LENGTH,
    CUSTOMER_PHONE_MAX_LENGTH,
} from '../types';
import { CustomerFormSection } from './CustomerFormSection';
import { CustomerPhotoField } from './CustomerPhotoField';
import type { CustomerFormController } from './use-customer-form';

const COUNTRY_NAME_KEYS = {
    MX: 'customers.form.phone.countries.MX',
    US: 'customers.form.phone.countries.US',
} as const satisfies Record<PhoneCountryCode, string>;

type Props = {
    form: CustomerFormController;
    focusNameField: boolean;
};

export function CustomerDetailsFields({ form, focusNameField }: Props) {
    const { t } = useTranslation('admin');
    const nameRef = useInitialFocus<HTMLInputElement>(focusNameField);

    function selectPhoneCountry(code: string): void {
        const country = SUPPORTED_PHONE_COUNTRIES.find((supported) => supported.code === code);

        if (country !== undefined) {
            form.update('phoneCountry', country.code);
        }
    }

    return (
        <CustomerFormSection title={t('customers.form.details.title')}>
            <div className="grid gap-5 sm:grid-cols-[auto_minmax(0,1fr)] sm:items-start">
                <div className="sm:w-32">
                    <CustomerPhotoField
                        shownUrl={form.photo.shownUrl}
                        onSelect={form.photo.select}
                        onClear={form.photo.clear}
                    />
                </div>

                <div className="grid gap-5">
                    <FormField
                        ref={nameRef}
                        id="customer-name"
                        label={t('customers.form.name.label')}
                        placeholder={t('customers.form.name.placeholder')}
                        autoComplete="name"
                        required
                        maxLength={CUSTOMER_NAME_MAX_LENGTH}
                        value={form.values.name}
                        onChange={(event) => form.update('name', event.target.value)}
                        error={form.errorFor('name')}
                    />

                    <PhoneField
                        id="customer-phone"
                        label={t('customers.form.phone.label')}
                        countryLabel={t('customers.form.phone.country')}
                        numberLabel={t('customers.form.phone.number')}
                        countries={SUPPORTED_PHONE_COUNTRIES.map((country) => ({
                            code: country.code,
                            name: t(COUNTRY_NAME_KEYS[country.code]),
                            dialCode: country.dialCode,
                        }))}
                        country={form.values.phoneCountry}
                        onCountryChange={selectPhoneCountry}
                        number={form.values.phoneNumber}
                        onNumberChange={(value) =>
                            form.update('phoneNumber', value.slice(0, CUSTOMER_PHONE_MAX_LENGTH))
                        }
                        error={form.errorFor('phoneNumber')}
                    />
                </div>
            </div>

            <FormField
                id="customer-email"
                type="email"
                inputMode="email"
                label={t('customers.form.email.label')}
                placeholder={t('customers.form.email.placeholder')}
                autoComplete="email"
                autoCapitalize="none"
                spellCheck={false}
                maxLength={CUSTOMER_EMAIL_MAX_LENGTH}
                value={form.values.email}
                onChange={(event) => form.update('email', event.target.value)}
                error={form.errorFor('email')}
            />

            <DateField
                id="customer-birth-date"
                label={t('customers.form.birthDate.label')}
                clearable
                max={todayAsIsoDate()}
                value={form.values.birthDate}
                onChange={(value) => form.update('birthDate', value)}
                format={{
                    day: t('customers.form.birthDate.format.day'),
                    month: t('customers.form.birthDate.format.month'),
                    year: t('customers.form.birthDate.format.year'),
                }}
                messages={{
                    calendar: t('customers.form.birthDate.picker.calendar'),
                    previousMonth: t('customers.form.birthDate.picker.previousMonth'),
                    nextMonth: t('customers.form.birthDate.picker.nextMonth'),
                    month: t('customers.form.birthDate.picker.month'),
                    year: t('customers.form.birthDate.picker.year'),
                    today: t('customers.form.birthDate.picker.today'),
                    clear: t('customers.form.birthDate.picker.clear'),
                }}
                error={form.errorFor('birthDate')}
            />

            <TextareaField
                id="customer-notes"
                label={t('customers.form.notes.label')}
                placeholder={t('customers.form.notes.placeholder')}
                maxLength={CUSTOMER_NOTES_MAX_LENGTH}
                value={form.values.notes}
                onChange={(event) => form.update('notes', event.target.value)}
                hint={t('customers.form.notes.hint')}
                error={form.errorFor('notes')}
            />
        </CustomerFormSection>
    );
}

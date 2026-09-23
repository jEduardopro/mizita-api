import { useTranslation } from 'react-i18next';
import type { Customer } from '../types';
import { CustomerAddressLines } from './CustomerAddressLines';
import { CustomerAvatar } from './CustomerAvatar';
import { CustomerDetailRow } from './CustomerDetailRow';
import { formatPhone } from './customer-format';

const BIRTH_DATE_FORMAT: Intl.DateTimeFormatOptions = {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    timeZone: 'UTC',
};

function formatBirthDate(birthDate: string, locale: string): string {
    const civilDate = new Date(`${birthDate}T00:00:00Z`);

    return Number.isNaN(civilDate.getTime())
        ? birthDate
        : new Intl.DateTimeFormat(locale, BIRTH_DATE_FORMAT).format(civilDate);
}

type Props = {
    customer: Customer;
};

export function CustomerAboutPanel({ customer }: Props) {
    const { t, i18n } = useTranslation('admin');

    const notProvided = <span className="text-muted-foreground">{t('customers.notProvided')}</span>;
    const phone = formatPhone(customer.phone);

    return (
        <section className="rounded-2xl border border-border bg-card p-5 sm:p-6">
            <dl className="grid">
                <CustomerDetailRow
                    label={t('customers.form.photo.label')}
                    value={
                        customer.photo_url === null ? (
                            notProvided
                        ) : (
                            <CustomerAvatar
                                name={customer.name}
                                photoUrl={customer.photo_url}
                                size="lg"
                            />
                        )
                    }
                />

                <CustomerDetailRow
                    label={t('customers.form.email.label')}
                    value={
                        customer.email === null ? (
                            notProvided
                        ) : (
                            <a
                                href={`mailto:${customer.email}`}
                                className="break-all underline-offset-4 hover:underline focus-visible:underline"
                            >
                                {customer.email}
                            </a>
                        )
                    }
                />

                <CustomerDetailRow
                    label={t('customers.form.phone.label')}
                    value={
                        phone === null ? (
                            notProvided
                        ) : (
                            <a
                                href={`tel:${phone.replace(/\s/g, '')}`}
                                className="underline-offset-4 hover:underline focus-visible:underline"
                            >
                                {phone}
                            </a>
                        )
                    }
                />

                <CustomerDetailRow
                    label={t('customers.form.birthDate.label')}
                    value={
                        customer.birth_date === null
                            ? notProvided
                            : formatBirthDate(customer.birth_date, i18n.language)
                    }
                />

                <CustomerDetailRow
                    label={t('customers.form.address.title')}
                    value={
                        customer.address === null ? (
                            notProvided
                        ) : (
                            <CustomerAddressLines address={customer.address} />
                        )
                    }
                />
            </dl>
        </section>
    );
}

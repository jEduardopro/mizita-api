import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import type { Customer } from '../types';
import { CustomerAvatar } from './CustomerAvatar';
import { customerShowUrl } from './customer-urls';
import { contactSummary } from './customer-format';
import { CustomerRowActions } from './CustomerRowActions';

const CONTACT_SEPARATOR = ' · ';

type Props = {
    customer: Customer;
};

export function CustomerListRow({ customer }: Props) {
    const { t } = useTranslation('admin');

    const contact = contactSummary(customer);

    return (
        <article className="flex items-center gap-3 rounded-xl border border-border bg-card py-2.5 pr-2.5 pl-4">
            <CustomerAvatar name={customer.name} photoUrl={customer.photo_url} size="lg" />

            <div className="grid min-w-0 flex-1 gap-0.5">
                <Link
                    href={customerShowUrl(customer.id)}
                    className="truncate text-sm font-medium outline-none hover:underline focus-visible:underline"
                >
                    {customer.name}
                </Link>

                <p className="truncate text-xs text-muted-foreground">
                    {contact.length === 0
                        ? t('customers.notProvided')
                        : contact.join(CONTACT_SEPARATOR)}
                </p>
            </div>

            <CustomerRowActions customer={customer} />
        </article>
    );
}

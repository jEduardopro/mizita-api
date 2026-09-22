import { useTranslation } from 'react-i18next';
import { CustomerForm } from '@/domains/customers/components/CustomerForm';
import { CustomerFormActions } from '@/domains/customers/components/CustomerFormActions';
import { CustomerFormSkeleton } from '@/domains/customers/components/CustomerFormSkeleton';
import { CustomerLoadError } from '@/domains/customers/components/CustomerLoadError';
import { CUSTOMERS_URL, customerShowUrl } from '@/domains/customers/components/customer-urls';
import { useCustomerForm } from '@/domains/customers/components/use-customer-form';
import { useCustomer } from '@/domains/customers/queries';
import { useUrlQueryState } from '@/hooks/use-url-query-state';
import { AdminLayout } from '@/layouts/AdminLayout';
import { httpStatusFrom } from '@/lib/http';
import { RETURN_PARAMETER, safeReturnTo } from '@/lib/return-to';

const NOT_FOUND_STATUS = 404;

type Props = {
    customerId: string;
};

export default function EditCustomer({ customerId }: Props) {
    const { t } = useTranslation('admin');
    const customer = useCustomer(customerId);
    const returnTo = safeReturnTo(useUrlQueryState().read(RETURN_PARAMETER), CUSTOMERS_URL);
    const form = useCustomerForm({ mode: 'edit', customer: customer.data ?? null, returnTo });

    return (
        <AdminLayout
            title={t('customers.edit.title')}
            description={t('customers.edit.description')}
            breadcrumbs={[
                { label: t('customers.title'), href: CUSTOMERS_URL },
                {
                    label: customer.data?.name ?? t('customers.show.title'),
                    href: customerShowUrl(customerId),
                },
                { label: t('customers.edit.title') },
            ]}
            actions={
                customer.data ? (
                    <CustomerFormActions mode="edit" form={form} returnTo={returnTo} />
                ) : null
            }
        >
            {customer.isPending ? <CustomerFormSkeleton /> : null}

            {customer.isError ? (
                <CustomerLoadError
                    notFound={httpStatusFrom(customer.error) === NOT_FOUND_STATUS}
                    onRetry={() => void customer.refetch()}
                />
            ) : null}

            {customer.data ? <CustomerForm form={form} /> : null}
        </AdminLayout>
    );
}

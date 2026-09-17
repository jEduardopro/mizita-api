import { useTranslation } from 'react-i18next';
import { CustomerForm } from '@/domains/customers/components/CustomerForm';
import { CustomerFormActions } from '@/domains/customers/components/CustomerFormActions';
import { CustomerFormSkeleton } from '@/domains/customers/components/CustomerFormSkeleton';
import { CustomerLoadError } from '@/domains/customers/components/CustomerLoadError';
import { CUSTOMERS_URL } from '@/domains/customers/components/customer-urls';
import { useCustomerForm } from '@/domains/customers/components/use-customer-form';
import { useCustomer } from '@/domains/customers/queries';
import { AdminLayout } from '@/layouts/AdminLayout';
import { httpStatusFrom } from '@/lib/http';

const NOT_FOUND_STATUS = 404;

type Props = {
    customerId: string;
};

export default function EditCustomer({ customerId }: Props) {
    const { t } = useTranslation('admin');
    const customer = useCustomer(customerId);
    const form = useCustomerForm({ mode: 'edit', customer: customer.data ?? null });

    return (
        <AdminLayout
            title={t('customers.edit.title')}
            description={t('customers.edit.description')}
            breadcrumbs={[
                { label: t('customers.title'), href: CUSTOMERS_URL },
                { label: t('customers.edit.title') },
            ]}
            actions={customer.data ? <CustomerFormActions mode="edit" form={form} /> : null}
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

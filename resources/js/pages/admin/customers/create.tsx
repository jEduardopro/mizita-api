import { useTranslation } from 'react-i18next';
import { CustomerForm } from '@/domains/customers/components/CustomerForm';
import { CustomerFormActions } from '@/domains/customers/components/CustomerFormActions';
import { CUSTOMERS_URL } from '@/domains/customers/components/customer-urls';
import { useCustomerForm } from '@/domains/customers/components/use-customer-form';
import { AdminLayout } from '@/layouts/AdminLayout';

export default function CreateCustomer() {
    const { t } = useTranslation('admin');
    const form = useCustomerForm({ mode: 'create', customer: null });

    return (
        <AdminLayout
            title={t('customers.create.title')}
            description={t('customers.create.description')}
            breadcrumbs={[
                { label: t('customers.title'), href: CUSTOMERS_URL },
                { label: t('customers.create.title') },
            ]}
            actions={<CustomerFormActions mode="create" form={form} returnTo={CUSTOMERS_URL} />}
        >
            <CustomerForm form={form} focusNameField />
        </AdminLayout>
    );
}

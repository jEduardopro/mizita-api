import { useTranslation } from 'react-i18next';
import { ServiceForm } from '@/domains/services/components/ServiceForm';
import { ServiceFormActions } from '@/domains/services/components/ServiceFormActions';
import { SERVICES_URL } from '@/domains/services/components/service-urls';
import { useServiceForm } from '@/domains/services/components/use-service-form';
import { useStaffChoices } from '@/domains/staff/queries';
import { AdminLayout } from '@/layouts/AdminLayout';

export default function CreateService() {
    const { t } = useTranslation('admin');
    const staff = useStaffChoices();
    const form = useServiceForm({ mode: 'create', service: null });

    return (
        <AdminLayout
            title={t('services.create.title')}
            description={t('services.create.description')}
            breadcrumbs={[
                { label: t('services.title'), href: SERVICES_URL },
                { label: t('services.create.title') },
            ]}
            actions={<ServiceFormActions mode="create" form={form} />}
        >
            <ServiceForm form={form} staff={staff} />
        </AdminLayout>
    );
}

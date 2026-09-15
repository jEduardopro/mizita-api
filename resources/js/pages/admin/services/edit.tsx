import { useTranslation } from 'react-i18next';
import { ServiceForm } from '@/domains/services/components/ServiceForm';
import { ServiceFormActions } from '@/domains/services/components/ServiceFormActions';
import { ServiceFormSkeleton } from '@/domains/services/components/ServiceFormSkeleton';
import { ServiceLoadError } from '@/domains/services/components/ServiceLoadError';
import { SERVICES_URL } from '@/domains/services/components/service-urls';
import { useServiceForm } from '@/domains/services/components/use-service-form';
import { useService } from '@/domains/services/queries';
import { useStaffChoices } from '@/domains/staff/queries';
import { AdminLayout } from '@/layouts/AdminLayout';
import { httpStatusFrom } from '@/lib/http';

const NOT_FOUND_STATUS = 404;

type Props = {
    serviceId: string;
};

export default function EditService({ serviceId }: Props) {
    const { t } = useTranslation('admin');
    const staff = useStaffChoices();
    const service = useService(serviceId);
    const form = useServiceForm({ mode: 'edit', service: service.data ?? null });

    return (
        <AdminLayout
            title={t('services.edit.title')}
            description={t('services.edit.description')}
            breadcrumbs={[
                { label: t('services.title'), href: SERVICES_URL },
                { label: t('services.edit.title') },
            ]}
            actions={service.data ? <ServiceFormActions mode="edit" form={form} /> : null}
        >
            {service.isPending ? <ServiceFormSkeleton /> : null}

            {service.isError ? (
                <ServiceLoadError
                    notFound={httpStatusFrom(service.error) === NOT_FOUND_STATUS}
                    onRetry={() => void service.refetch()}
                />
            ) : null}

            {service.data ? <ServiceForm form={form} staff={staff} /> : null}
        </AdminLayout>
    );
}

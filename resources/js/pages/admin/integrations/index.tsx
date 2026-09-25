import { useTranslation } from 'react-i18next';
import { useMyBusinesses } from '@/domains/businesses/queries';
import { IntegrationsDirectory } from '@/domains/integrations/components/IntegrationsDirectory';
import { IntegrationsLoadError } from '@/domains/integrations/components/IntegrationsLoadError';
import { IntegrationsSkeleton } from '@/domains/integrations/components/IntegrationsSkeleton';
import { useIntegrations } from '@/domains/integrations/queries';
import { GOOGLE_CALENDAR_CONNECTED_STATUS } from '@/domains/integrations/types';
import { useStatusToast } from '@/hooks/use-status-toast';
import { AdminLayout } from '@/layouts/AdminLayout';

type ContentProps = {
    businessName: string | null;
};

function IntegrationsContent({ businessName }: ContentProps) {
    const integrations = useIntegrations();

    if (integrations.isPending) {
        return <IntegrationsSkeleton />;
    }

    if (integrations.isError) {
        return (
            <IntegrationsLoadError
                isRetrying={integrations.isFetching}
                onRetry={() => void integrations.refetch()}
            />
        );
    }

    return <IntegrationsDirectory integrations={integrations.data} businessName={businessName} />;
}

export default function IntegrationsIndex() {
    const { t } = useTranslation('admin');
    const { data: businesses } = useMyBusinesses();

    useStatusToast(GOOGLE_CALENDAR_CONNECTED_STATUS, t('integrations.googleCalendar.connected'));

    return (
        <AdminLayout title={t('integrations.title')}>
            <IntegrationsContent businessName={businesses?.[0]?.name ?? null} />
        </AdminLayout>
    );
}

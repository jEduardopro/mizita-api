import { useTranslation } from 'react-i18next';
import { BRAND_SETTINGS_URL } from '@/domains/businesses/components/settings-urls';
import { useBusinessTimezone } from '@/domains/businesses/queries';
import { SubscriptionPlanOverview } from '@/domains/subscriptions/components/SubscriptionPlanOverview';
import { useAuthorization } from '@/hooks/use-authorization';
import { AdminLayout } from '@/layouts/AdminLayout';
import { resolvedTimezone } from '@/lib/timezone';

export default function PlanSettings() {
    const { t } = useTranslation('admin');
    const { is } = useAuthorization();
    const timezone = useBusinessTimezone() ?? resolvedTimezone();

    return (
        <AdminLayout
            title={t('plan.settings.title')}
            breadcrumbs={[
                { label: t('nav.settings'), href: BRAND_SETTINGS_URL },
                { label: t('plan.settings.nav') },
            ]}
        >
            {is('owner') ? <SubscriptionPlanOverview timezone={timezone} /> : null}
        </AdminLayout>
    );
}

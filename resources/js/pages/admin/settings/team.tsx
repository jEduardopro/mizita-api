import { useTranslation } from 'react-i18next';
import { ComingSoon } from '@/components/admin/ComingSoon';
import { BRAND_SETTINGS_URL } from '@/domains/businesses/components/settings-urls';
import { AdminLayout } from '@/layouts/AdminLayout';

export default function Team() {
    const { t } = useTranslation('admin');

    return (
        <AdminLayout
            title={t('team.title')}
            description={t('team.description')}
            breadcrumbs={[
                { label: t('nav.settings'), href: BRAND_SETTINGS_URL },
                { label: t('nav.team') },
            ]}
        >
            <ComingSoon />
        </AdminLayout>
    );
}

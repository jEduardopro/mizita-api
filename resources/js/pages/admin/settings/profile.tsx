import { useTranslation } from 'react-i18next';
import { ComingSoon } from '@/components/admin/ComingSoon';
import { AdminLayout } from '@/layouts/AdminLayout';

export default function Profile() {
    const { t } = useTranslation('admin');

    return (
        <AdminLayout title={t('profile.title')} description={t('profile.description')}>
            <ComingSoon />
        </AdminLayout>
    );
}

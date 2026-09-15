import { useTranslation } from 'react-i18next';
import { ComingSoon } from '@/components/admin/ComingSoon';
import { AdminLayout } from '@/layouts/AdminLayout';

export default function Services() {
    const { t } = useTranslation('admin');

    return (
        <AdminLayout title={t('services.title')}>
            <ComingSoon />
        </AdminLayout>
    );
}

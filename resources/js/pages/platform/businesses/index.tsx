import { useTranslation } from 'react-i18next';
import { PlatformBusinessesTable } from '@/domains/platform/components/PlatformBusinessesTable';
import { PlatformLayout } from '@/layouts/PlatformLayout';

export default function PlatformBusinessesIndex() {
    const { t } = useTranslation('platform');

    return (
        <PlatformLayout title={t('businesses.title')} description={t('businesses.description')}>
            <PlatformBusinessesTable />
        </PlatformLayout>
    );
}

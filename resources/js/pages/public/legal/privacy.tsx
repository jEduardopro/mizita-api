import { useTranslation } from 'react-i18next';
import { LegalDocument } from '@/components/public/legal/LegalDocument';
import { PublicLayout } from '@/layouts/PublicLayout';

export default function Privacy() {
    const { t } = useTranslation('common');

    return (
        <PublicLayout title={t('legal.titles.privacy')}>
            <LegalDocument document="privacy" />
        </PublicLayout>
    );
}

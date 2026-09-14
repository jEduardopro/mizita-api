import { useTranslation } from 'react-i18next';
import { LegalDocument } from '@/components/public/legal/LegalDocument';
import { PublicLayout } from '@/layouts/PublicLayout';

export default function Terms() {
    const { t } = useTranslation('common');

    return (
        <PublicLayout title={t('legal.titles.terms')}>
            <LegalDocument document="terms" />
        </PublicLayout>
    );
}

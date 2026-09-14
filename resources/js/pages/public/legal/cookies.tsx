import { useTranslation } from 'react-i18next';
import { LegalDocument } from '@/components/public/legal/LegalDocument';
import { PublicLayout } from '@/layouts/PublicLayout';

export default function Cookies() {
    const { t } = useTranslation('common');

    return (
        <PublicLayout title={t('legal.titles.cookies')}>
            <LegalDocument document="cookies" />
        </PublicLayout>
    );
}

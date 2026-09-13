import { useTranslation } from 'react-i18next';
import { LegalDocument } from '@/components/public/legal/LegalDocument';
import { PublicLayout } from '@/layouts/PublicLayout';

/** The privacy notice, rendered by `Inertia::render('public/legal/privacy')`. */
export default function Privacy() {
    const { t } = useTranslation('common');

    return (
        <PublicLayout title={t('legal.titles.privacy')}>
            <LegalDocument document="privacy" />
        </PublicLayout>
    );
}

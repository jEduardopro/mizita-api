import { useTranslation } from 'react-i18next';
import { LegalDocument } from '@/components/public/legal/LegalDocument';
import { PublicLayout } from '@/layouts/PublicLayout';

/**
 * The terms of service, rendered by `Inertia::render('public/legal/terms')`.
 *
 * No `sections` on the layout: the header menu belongs to the landing page,
 * and a document navigates by its own index rather than by the page chrome.
 */
export default function Terms() {
    const { t } = useTranslation('common');

    return (
        <PublicLayout title={t('legal.titles.terms')}>
            <LegalDocument document="terms" />
        </PublicLayout>
    );
}

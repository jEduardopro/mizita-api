import { useTranslation } from 'react-i18next';
import { SettingsSubnav } from '@/components/admin/settings/SettingsSubnav';
import { SubmitButton } from '@/components/form/SubmitButton';
import { Skeleton } from '@/components/ui/skeleton';
import { BRAND_SETTINGS_URL } from '@/domains/businesses/components/settings-urls';
import { BookingPagePreview } from '@/domains/businesses/components/settings/BookingPagePreview';
import { BUSINESS_SETTINGS_SECTIONS } from '@/domains/businesses/components/settings/business-settings-values';
import { BusinessSettingsForm } from '@/domains/businesses/components/settings/BusinessSettingsForm';
import { SettingsLoadError } from '@/domains/businesses/components/settings/SettingsLoadError';
import {
    BUSINESS_SETTINGS_FORM_ID,
    useBusinessSettingsForm,
} from '@/domains/businesses/components/settings/use-business-settings-form';
import { AdminLayout } from '@/layouts/AdminLayout';

const SKELETON_SECTIONS = [0, 1, 2, 3];

const COLUMNS =
    'grid items-start gap-8 md:grid-cols-[10rem_minmax(0,1fr)] lg:grid-cols-[11rem_minmax(0,1fr)_17rem] xl:grid-cols-[13rem_minmax(0,1fr)_22rem]';

function BusinessSettingsSkeleton() {
    return (
        <div role="status" aria-busy="true" className={COLUMNS}>
            <Skeleton className="h-11 rounded-full md:h-56 md:rounded-xl" />

            <div className="grid gap-5">
                {SKELETON_SECTIONS.map((section) => (
                    <Skeleton key={section} className="h-40 rounded-xl" />
                ))}
            </div>

            <Skeleton className="hidden h-96 rounded-2xl lg:block" />
        </div>
    );
}

export default function BusinessSettings() {
    const { t } = useTranslation('admin');
    const form = useBusinessSettingsForm();

    const isReady = ! form.isLoading && ! form.isLoadError;

    const sections = BUSINESS_SETTINGS_SECTIONS.map((section) => ({
        id: section.id,
        label: t(section.labelKey),
    }));

    return (
        <AdminLayout
            title={t('businessSettings.title')}
            description={t('businessSettings.description')}
            breadcrumbs={[
                { label: t('nav.settings'), href: BRAND_SETTINGS_URL },
                { label: t('nav.brand') },
            ]}
            actions={
                isReady ? (
                    <SubmitButton
                        form={BUSINESS_SETTINGS_FORM_ID}
                        variant="brand"
                        className="h-11 px-4 md:h-9"
                        label={t('businessSettings.submit.save')}
                        submittingLabel={t('businessSettings.submit.saving')}
                        isSubmitting={form.isSubmitting}
                    />
                ) : null
            }
        >
            {form.isLoading ? <BusinessSettingsSkeleton /> : null}

            {form.isLoadError ? <SettingsLoadError onRetry={form.retry} /> : null}

            {isReady ? (
                <div className={COLUMNS}>
                    <SettingsSubnav sections={sections} />

                    <BusinessSettingsForm form={form} />

                    <div className="md:col-span-2 lg:sticky lg:top-32 lg:col-span-1 lg:max-h-[calc(100svh-10rem)] lg:overflow-y-auto">
                        <BookingPagePreview
                            values={form.values}
                            logoUrl={form.logo.shownUrl}
                            bannerUrl={form.banner.shownUrl}
                            savedSlug={form.savedSlug}
                            policyNotice={form.policyNotice}
                        />
                    </div>
                </div>
            ) : null}
        </AdminLayout>
    );
}

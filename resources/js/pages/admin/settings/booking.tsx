import { useTranslation } from 'react-i18next';
import { SettingsSubnav } from '@/components/admin/settings/SettingsSubnav';
import { SubmitButton } from '@/components/form/SubmitButton';
import { Skeleton } from '@/components/ui/skeleton';
import { BOOKING_PREFERENCES_SECTIONS } from '@/domains/businesses/components/booking-preferences/booking-preferences-values';
import { BookingPreferencesForm } from '@/domains/businesses/components/booking-preferences/BookingPreferencesForm';
import {
    BOOKING_PREFERENCES_FORM_ID,
    useBookingPreferencesForm,
} from '@/domains/businesses/components/booking-preferences/use-booking-preferences-form';
import { BRAND_SETTINGS_URL } from '@/domains/businesses/components/settings-urls';
import { SettingsLoadError } from '@/domains/businesses/components/settings/SettingsLoadError';
import { AdminLayout } from '@/layouts/AdminLayout';

const SKELETON_SECTIONS = [0, 1];

const COLUMNS =
    'grid items-start gap-8 md:grid-cols-[10rem_minmax(0,1fr)] lg:grid-cols-[11rem_minmax(0,48rem)] xl:grid-cols-[13rem_minmax(0,52rem)]';

function BookingPreferencesSkeleton() {
    return (
        <div role="status" aria-busy="true" className={COLUMNS}>
            <Skeleton className="h-11 rounded-full md:h-24 md:rounded-xl" />

            <div className="grid gap-5">
                {SKELETON_SECTIONS.map((section) => (
                    <Skeleton key={section} className="h-64 rounded-xl" />
                ))}
            </div>
        </div>
    );
}

export default function BookingPreferences() {
    const { t } = useTranslation('admin');
    const form = useBookingPreferencesForm();

    const isReady = ! form.isLoading && ! form.isLoadError;

    const sections = BOOKING_PREFERENCES_SECTIONS.map((section) => ({
        id: section.id,
        label: t(section.labelKey),
    }));

    return (
        <AdminLayout
            title={t('bookingPreferences.title')}
            description={t('bookingPreferences.description')}
            breadcrumbs={[
                { label: t('nav.settings'), href: BRAND_SETTINGS_URL },
                { label: t('nav.bookingPreferences') },
            ]}
            actions={
                isReady ? (
                    <SubmitButton
                        form={BOOKING_PREFERENCES_FORM_ID}
                        variant="brand"
                        className="h-11 px-4 md:h-9"
                        label={t('bookingPreferences.submit.save')}
                        submittingLabel={t('bookingPreferences.submit.saving')}
                        isSubmitting={form.isSubmitting}
                    />
                ) : null
            }
        >
            {form.isLoading ? <BookingPreferencesSkeleton /> : null}

            {form.isLoadError ? <SettingsLoadError onRetry={form.retry} /> : null}

            {isReady ? (
                <div className={COLUMNS}>
                    <SettingsSubnav sections={sections} />

                    <BookingPreferencesForm form={form} />
                </div>
            ) : null}
        </AdminLayout>
    );
}

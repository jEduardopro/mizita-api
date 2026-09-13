import { useTranslation } from 'react-i18next';
import { BusinessOnboardingForm } from '@/domains/businesses/components/BusinessOnboardingForm';
import { useIndustryOptions } from '@/domains/industries/queries';
import { OnboardingLayout } from '@/layouts/OnboardingLayout';

/**
 * Rendered by `Inertia::render('admin/onboarding')`.
 *
 * No data arrives as a prop: the page mounts and the hooks fetch from `/api`,
 * the same endpoints a native client will call.
 *
 * The industry catalogue is read here and handed to the form because this is
 * where two domains legitimately meet. Businesses and Industries never import
 * each other; a page composes them.
 */
export default function Onboarding() {
    const { t } = useTranslation('admin');
    const industries = useIndustryOptions();

    return (
        <OnboardingLayout title={t('onboarding.title')}>
            <div className="mb-7">
                <h1 className="font-heading text-[clamp(1.5rem,6vw,1.875rem)] font-medium tracking-[-0.03em] text-balance">
                    {t('onboarding.heading')}
                </h1>

                <p className="mt-2 text-sm leading-relaxed text-pretty text-muted-foreground">
                    {t('onboarding.description')}
                </p>
            </div>

            <BusinessOnboardingForm industries={industries} />
        </OnboardingLayout>
    );
}

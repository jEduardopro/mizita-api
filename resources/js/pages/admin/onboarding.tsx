import { useTranslation } from 'react-i18next';
import { BusinessOnboardingForm } from '@/domains/businesses/components/BusinessOnboardingForm';
import { useIndustryOptions } from '@/domains/industries/queries';
import { OnboardingLayout } from '@/layouts/OnboardingLayout';

/**
 * The industry catalogue is read here and handed to the form: Businesses and
 * Industries never import each other, and a page is where they meet.
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

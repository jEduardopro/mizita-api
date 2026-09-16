import { useTranslation } from 'react-i18next';
import { ComboboxField, comboboxOptionsStatus } from '@/components/form/ComboboxField';
import { FormField } from '@/components/form/FormField';
import { TextareaField } from '@/components/form/TextareaField';
import { useIndustryOptions } from '@/domains/industries/queries';
import { BannerField } from './BannerField';
import { BookingUrlField } from './BookingUrlField';
import { BUSINESS_SETTINGS_SECTION_IDS } from './business-settings-values';
import { LogoField } from './LogoField';
import { SettingsSection } from './SettingsSection';
import type { BusinessSettingsFormController } from './use-business-settings-form';

const MAXIMUM_NAME_LENGTH = 120;

type Props = {
    form: BusinessSettingsFormController;
};

export function BrandDetailsSection({ form }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');
    const industries = useIndustryOptions();

    return (
        <SettingsSection
            id={BUSINESS_SETTINGS_SECTION_IDS.brand}
            title={t('businessSettings.brand.title')}
            description={t('businessSettings.brand.description')}
        >
            <div className="grid gap-5 sm:grid-cols-[13rem_minmax(0,1fr)] sm:items-start">
                <LogoField
                    shownUrl={form.logo.shownUrl}
                    onSelect={form.logo.select}
                    onClear={form.logo.clear}
                />

                <BannerField
                    shownUrl={form.banner.shownUrl}
                    onSelect={form.banner.select}
                    onClear={form.banner.clear}
                />
            </div>

            <FormField
                id="business-name"
                label={t('businessSettings.brand.name.label')}
                placeholder={t('businessSettings.brand.name.placeholder')}
                autoComplete="organization"
                required
                maxLength={MAXIMUM_NAME_LENGTH}
                value={form.values.name}
                onChange={(event) => form.update('name', event.target.value)}
                error={form.errorFor('name')}
            />

            <BookingUrlField
                value={form.values.slug}
                savedSlug={form.savedSlug}
                onChange={(value) => form.update('slug', value)}
                error={form.errorFor('slug')}
            />

            <ComboboxField
                id="business-industry"
                label={t('businessSettings.brand.industry.label')}
                placeholder={t('businessSettings.brand.industry.placeholder')}
                options={industries.options}
                value={form.values.industryId}
                onChange={(value) => form.update('industryId', value)}
                optionsStatus={comboboxOptionsStatus(industries.isPending, industries.isError)}
                onRetryOptions={industries.refetch}
                messages={{
                    empty: t('businessSettings.brand.industry.empty'),
                    optionsError: t('businessSettings.brand.industry.error'),
                    retry: tCommon('actions.tryAgain'),
                    results: industries.isPending
                        ? t('businessSettings.brand.industry.loading')
                        : t('businessSettings.brand.industry.results', {
                              count: industries.options.length,
                          }),
                }}
                error={form.errorFor('industryId')}
            />

            <TextareaField
                id="business-about"
                label={t('businessSettings.brand.about.label')}
                placeholder={t('businessSettings.brand.about.placeholder')}
                rows={5}
                value={form.values.about}
                onChange={(event) => form.update('about', event.target.value)}
                hint={t('businessSettings.brand.about.hint')}
                error={form.errorFor('about')}
            />
        </SettingsSection>
    );
}

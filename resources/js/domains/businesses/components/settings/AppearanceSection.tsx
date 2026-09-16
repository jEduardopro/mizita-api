import { useTranslation } from 'react-i18next';
import { BrandColorField } from './BrandColorField';
import { BUSINESS_SETTINGS_SECTION_IDS } from './business-settings-values';
import { ButtonShapeField } from './ButtonShapeField';
import { GalleryField } from './GalleryField';
import { SettingsSection } from './SettingsSection';
import { ThemeField } from './ThemeField';
import type { BusinessSettingsFormController } from './use-business-settings-form';

type Props = {
    form: BusinessSettingsFormController;
};

export function AppearanceSection({ form }: Props) {
    const { t } = useTranslation('admin');

    return (
        <SettingsSection
            id={BUSINESS_SETTINGS_SECTION_IDS.appearance}
            title={t('businessSettings.appearance.title')}
            description={t('businessSettings.appearance.description')}
        >
            <BrandColorField
                value={form.values.accentColor}
                onChange={(value) => form.update('accentColor', value)}
                error={form.errorFor('accentColor')}
            />

            <div className="grid gap-5 lg:grid-cols-2">
                <ButtonShapeField
                    value={form.values.buttonShape}
                    onChange={(value) => form.update('buttonShape', value)}
                    error={form.errorFor('buttonShape')}
                />

                <ThemeField
                    value={form.values.theme}
                    onChange={(value) => form.update('theme', value)}
                    error={form.errorFor('theme')}
                />
            </div>

            <GalleryField
                images={form.gallery.images}
                onAdd={form.gallery.add}
                onRemove={form.gallery.remove}
                onReorder={form.gallery.reorder}
            />
        </SettingsSection>
    );
}

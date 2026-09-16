import { Info } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { BUSINESS_SETTINGS_SECTION_IDS } from './business-settings-values';
import { SettingsSection } from './SettingsSection';
import type { BusinessSettingsFormController } from './use-business-settings-form';
import { WeeklyHoursField } from './WeeklyHoursField';

type Props = {
    form: BusinessSettingsFormController;
};

export function BusinessHoursSection({ form }: Props) {
    const { t } = useTranslation('admin');

    return (
        <SettingsSection
            id={BUSINESS_SETTINGS_SECTION_IDS.hours}
            title={t('businessSettings.hours.title')}
            description={t('businessSettings.hours.description')}
        >
            <WeeklyHoursField
                value={form.values.hours}
                onChange={(value) => form.update('hours', value)}
                error={form.errorFor('hours')}
            />

            <p className="flex items-start gap-2 rounded-xl bg-muted px-4 py-3 text-sm text-pretty text-muted-foreground">
                <Info aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
                {t('businessSettings.hours.staffNote')}
            </p>
        </SettingsSection>
    );
}

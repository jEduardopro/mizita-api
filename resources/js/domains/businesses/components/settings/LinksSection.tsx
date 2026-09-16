import { useTranslation } from 'react-i18next';
import { fieldMessage, FieldMessage } from '@/components/form/FieldMessage';
import { FormField } from '@/components/form/FormField';
import { LINK_PLATFORMS, type LinkPlatform } from '@/lib/booking-brand';
import { BUSINESS_SETTINGS_SECTION_IDS } from './business-settings-values';
import { SettingsSection } from './SettingsSection';
import type { BusinessSettingsFormController } from './use-business-settings-form';

const PLATFORM_LABEL_KEYS = {
    website: 'businessSettings.links.platforms.website',
    instagram: 'businessSettings.links.platforms.instagram',
    facebook: 'businessSettings.links.platforms.facebook',
    tiktok: 'businessSettings.links.platforms.tiktok',
    x: 'businessSettings.links.platforms.x',
    linkedin: 'businessSettings.links.platforms.linkedin',
    youtube: 'businessSettings.links.platforms.youtube',
    whatsapp: 'businessSettings.links.platforms.whatsapp',
} as const satisfies Record<LinkPlatform, string>;

const PLACEHOLDERS = {
    website: 'https://',
    instagram: 'https://instagram.com/',
    facebook: 'https://facebook.com/',
    tiktok: 'https://tiktok.com/@',
    x: 'https://x.com/',
    linkedin: 'https://linkedin.com/in/',
    youtube: 'https://youtube.com/@',
    whatsapp: 'https://wa.me/',
} as const satisfies Record<LinkPlatform, string>;

type Props = {
    form: BusinessSettingsFormController;
};

export function LinksSection({ form }: Props) {
    const { t } = useTranslation('admin');

    const message = fieldMessage({ id: 'business-links', error: form.errorFor('links') });

    return (
        <SettingsSection
            id={BUSINESS_SETTINGS_SECTION_IDS.links}
            title={t('businessSettings.links.title')}
            description={t('businessSettings.links.description')}
        >
            <div className="grid gap-5 lg:grid-cols-2">
                {LINK_PLATFORMS.map((platform) => (
                    <FormField
                        key={platform}
                        id={`business-link-${platform}`}
                        type="url"
                        inputMode="url"
                        label={t(PLATFORM_LABEL_KEYS[platform])}
                        placeholder={PLACEHOLDERS[platform]}
                        autoComplete="off"
                        autoCapitalize="none"
                        spellCheck={false}
                        value={form.values.links[platform]}
                        onChange={(event) =>
                            form.update('links', {
                                ...form.values.links,
                                [platform]: event.target.value,
                            })
                        }
                    />
                ))}
            </div>

            <FieldMessage message={message} />
        </SettingsSection>
    );
}

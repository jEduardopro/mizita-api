import { useTranslation } from 'react-i18next';
import { ImageField } from '@/components/form/ImageField';
import { IMAGE_MIME_TYPES, LOGO_MAXIMUM_BYTES } from './settings-media';

const FIELD_ID = 'business-logo';

type Props = {
    shownUrl: string | null;
    onSelect: (file: File) => void;
    onClear: () => void;
    error?: string;
};

export function LogoField({ shownUrl, onSelect, onClear, error }: Props) {
    const { t } = useTranslation('admin');

    return (
        <ImageField
            id={FIELD_ID}
            label={t('businessSettings.logo.label')}
            shownUrl={shownUrl}
            onSelect={onSelect}
            onClear={onClear}
            accept={IMAGE_MIME_TYPES}
            maximumBytes={LOGO_MAXIMUM_BYTES}
            hint={t('businessSettings.logo.hint')}
            error={error}
            tileClassName="size-50 rounded-full"
            messages={{
                choose: t('businessSettings.logo.choose'),
                tooLarge: t('businessSettings.logo.tooLarge'),
                upload: t('businessSettings.media.upload'),
                replace: t('businessSettings.media.replace'),
                remove: t('businessSettings.media.remove'),
                unsupported: t('businessSettings.media.unsupported'),
            }}
        />
    );
}

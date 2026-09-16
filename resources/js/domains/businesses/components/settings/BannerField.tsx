import { useTranslation } from 'react-i18next';
import { ImageField } from '@/components/form/ImageField';
import { BANNER_MAXIMUM_BYTES, IMAGE_MIME_TYPES } from './settings-media';

const FIELD_ID = 'booking-page-banner';

type Props = {
    shownUrl: string | null;
    onSelect: (file: File) => void;
    onClear: () => void;
    error?: string;
};

export function BannerField({ shownUrl, onSelect, onClear, error }: Props) {
    const { t } = useTranslation('admin');

    return (
        <ImageField
            id={FIELD_ID}
            label={t('businessSettings.banner.label')}
            shownUrl={shownUrl}
            onSelect={onSelect}
            onClear={onClear}
            accept={IMAGE_MIME_TYPES}
            maximumBytes={BANNER_MAXIMUM_BYTES}
            hint={t('businessSettings.banner.hint')}
            error={error}
            tileClassName="aspect-video w-full"
            messages={{
                choose: t('businessSettings.banner.choose'),
                tooLarge: t('businessSettings.banner.tooLarge'),
                upload: t('businessSettings.media.upload'),
                replace: t('businessSettings.media.replace'),
                remove: t('businessSettings.media.remove'),
                unsupported: t('businessSettings.media.unsupported'),
            }}
        />
    );
}

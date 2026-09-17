import { UserRound } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { ImageField } from '@/components/form/ImageField';
import { CUSTOMER_PHOTO_MAXIMUM_BYTES, CUSTOMER_PHOTO_MIME_TYPES } from '../types';

const FIELD_ID = 'customer-photo';

type Props = {
    shownUrl: string | null;
    onSelect: (file: File) => void;
    onClear: () => void;
};

export function CustomerPhotoField({ shownUrl, onSelect, onClear }: Props) {
    const { t } = useTranslation('admin');

    return (
        <ImageField
            id={FIELD_ID}
            label={t('customers.form.photo.label')}
            shownUrl={shownUrl}
            onSelect={onSelect}
            onClear={onClear}
            accept={CUSTOMER_PHOTO_MIME_TYPES}
            maximumBytes={CUSTOMER_PHOTO_MAXIMUM_BYTES}
            tileClassName="size-28 rounded-full sm:size-32"
            placeholderIcon={UserRound}
            messages={{
                choose: t('customers.form.photo.choose'),
                upload: t('customers.form.photo.upload'),
                replace: t('customers.form.photo.replace'),
                remove: t('customers.form.photo.remove'),
                tooLarge: t('customers.form.photo.tooLarge'),
                unsupported: t('customers.form.photo.unsupported'),
            }}
        />
    );
}

import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { formMessageFrom } from '@/lib/http';
import { raiseErrorToast, raiseSuccessToast } from '@/lib/toast';
import { PROFILE_PHOTO_MAXIMUM_BYTES, PROFILE_PHOTO_MIME_TYPES } from '../types';

const ACCEPTED_MIME_TYPES: readonly string[] = PROFILE_PHOTO_MIME_TYPES;

export type ProfilePhotoController = {
    accept: string;
    select: (file: File) => void;
    remove: () => void;
    isBusy: boolean;
};

type Params = {
    onUpload: (photo: File) => Promise<unknown>;
    onRemove: () => Promise<unknown>;
};

export function useProfilePhoto({ onUpload, onRemove }: Params): ProfilePhotoController {
    const { t } = useTranslation('admin');
    const [isBusy, setIsBusy] = useState(false);

    async function run(change: () => Promise<unknown>, successMessage: string) {
        setIsBusy(true);

        try {
            await change();
            raiseSuccessToast(successMessage);
        } catch (error) {
            raiseErrorToast(formMessageFrom(error, t('profile.photo.failed')));
        } finally {
            setIsBusy(false);
        }
    }

    function select(photo: File) {
        if (! ACCEPTED_MIME_TYPES.includes(photo.type)) {
            raiseErrorToast(t('profile.photo.unsupported'));

            return;
        }

        if (photo.size > PROFILE_PHOTO_MAXIMUM_BYTES) {
            raiseErrorToast(t('profile.photo.tooLarge'));

            return;
        }

        void run(() => onUpload(photo), t('profile.photo.uploaded'));
    }

    return {
        accept: ACCEPTED_MIME_TYPES.join(','),
        select,
        remove: () => void run(onRemove, t('profile.photo.removed')),
        isBusy,
    };
}

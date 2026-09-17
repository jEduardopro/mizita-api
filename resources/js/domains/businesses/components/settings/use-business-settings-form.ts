import { useCallback, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { useBusinessSettings, useUpdateBusinessSettings } from '@/domains/businesses/queries';
import { useAddressClearingGuard } from '@/hooks/use-address-clearing-guard';
import { useServerErrors } from '@/hooks/use-server-errors';
import { raiseErrorToast, raiseSuccessToast } from '@/lib/toast';
import {
    businessSettingsPayloadFrom,
    initialBusinessSettingsValues,
    serverFields,
    type BusinessSettingsField,
    type BusinessSettingsFormValues,
} from './business-settings-values';
import { useBusinessSettingsMedia } from './use-business-settings-media';
import type { GalleryDraft } from './use-gallery-draft';
import type { ImageDraft } from './use-image-draft';

export const BUSINESS_SETTINGS_FORM_ID = 'business-settings-form';

export type BusinessSettingsFormController = {
    values: BusinessSettingsFormValues;
    savedSlug: string;
    update: <TKey extends BusinessSettingsField>(
        key: TKey,
        value: BusinessSettingsFormValues[TKey],
    ) => void;
    errorFor: (field: BusinessSettingsField) => string | undefined;
    isRequired: (field: BusinessSettingsField) => boolean;
    isLoading: boolean;
    isLoadError: boolean;
    retry: () => void;
    isSubmitting: boolean;
    submit: (event: FormEvent<HTMLFormElement>) => void;
    logo: ImageDraft;
    banner: ImageDraft;
    gallery: GalleryDraft;
};

function matchesServerField(key: string, serverField: string): boolean {
    return key === serverField || key.startsWith(`${serverField}.`);
}

export function useBusinessSettingsForm(): BusinessSettingsFormController {
    const { t } = useTranslation('admin');
    const { fieldErrors, capture, clearField, reset } = useServerErrors();

    const settingsQuery = useBusinessSettings();
    const settings = settingsQuery.data ?? null;

    const [values, setValues] = useState(() => initialBusinessSettingsValues(settings));
    const [loadedSettingsId, setLoadedSettingsId] = useState(settings?.id ?? null);

    if ((settings?.id ?? null) !== loadedSettingsId) {
        setLoadedSettingsId(settings?.id ?? null);
        setValues(initialBusinessSettingsValues(settings));
    }

    const media = useBusinessSettingsMedia(settings);
    const addressGuard = useAddressClearingGuard({
        stored: settings?.address ?? null,
        values,
        fieldMessage: t('businessSettings.location.cannotClear.field'),
        blockedMessage: t('businessSettings.location.cannotClear.blocked'),
    });
    const updateSettings = useUpdateBusinessSettings();

    const update = useCallback(
        <TKey extends BusinessSettingsField>(key: TKey, value: BusinessSettingsFormValues[TKey]) => {
            setValues((current) => ({ ...current, [key]: value }));

            for (const field of Object.keys(fieldErrors)) {
                if (matchesServerField(field, serverFields[key])) {
                    clearField(field);
                }
            }
        },
        [fieldErrors, clearField],
    );

    const serverErrorFor = useCallback(
        (field: BusinessSettingsField) => {
            const key = Object.keys(fieldErrors).find((candidate) =>
                matchesServerField(candidate, serverFields[field]),
            );

            return key === undefined ? undefined : fieldErrors[key];
        },
        [fieldErrors],
    );

    const errorFor = useCallback(
        (field: BusinessSettingsField) => addressGuard.errorFor(field) ?? serverErrorFor(field),
        [addressGuard, serverErrorFor],
    );

    const retry = useCallback(() => void settingsQuery.refetch(), [settingsQuery]);

    async function save() {
        reset();

        if (! addressGuard.confirmSaveAllowed()) {
            return;
        }

        try {
            await updateSettings.mutateAsync(businessSettingsPayloadFrom(values));
        } catch (error) {
            addressGuard.captureRefusal(error);
            capture(error, t('businessSettings.errors.unexpected'));

            return;
        }

        if (! (await media.sync())) {
            raiseErrorToast(t('businessSettings.media.failed'));

            return;
        }

        raiseSuccessToast(t('businessSettings.toasts.saved'));
    }

    return {
        values,
        savedSlug: settings?.slug ?? '',
        update,
        errorFor,
        isRequired: addressGuard.isRequired,
        isLoading: settingsQuery.isPending,
        isLoadError: settingsQuery.isError,
        retry,
        isSubmitting: updateSettings.isPending || media.isSyncing,
        submit: (event: FormEvent<HTMLFormElement>) => {
            event.preventDefault();
            void save();
        },
        logo: media.logo,
        banner: media.banner,
        gallery: media.gallery,
    };
}

import { router } from '@inertiajs/react';
import { useCallback, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { useServerErrors } from '@/hooks/use-server-errors';
import { raiseErrorToast, raiseSuccessToast } from '@/lib/toast';
import {
    useAttachServiceImage,
    useCreateService,
    useRemoveServiceImage,
    useUpdateService,
} from '../queries';
import type { Service } from '../types';
import {
    initialServiceValues,
    serverFields,
    servicePayloadFrom,
    type ServiceField,
    type ServiceFormValues,
} from './service-form-values';
import { serviceEditUrl, SERVICES_URL } from './service-urls';

export type ServiceFormMode = 'create' | 'edit';

type ImageState = {
    file: File | null;
    previewUrl: string | null;
    select: (file: File) => void;
    clear: () => void;
};

export type ServiceFormController = {
    values: ServiceFormValues;
    update: <TKey extends ServiceField>(key: TKey, value: ServiceFormValues[TKey]) => void;
    errorFor: (field: ServiceField) => string | undefined;
    image: ImageState;
    isSubmitting: boolean;
    submit: (event: FormEvent<HTMLFormElement>) => void;
};

type Params = {
    mode: ServiceFormMode;
    service: Service | null;
};

export function useServiceForm({ mode, service }: Params): ServiceFormController {
    const { t } = useTranslation('admin');
    const { fieldErrors, capture, clearField, reset } = useServerErrors();

    const [values, setValues] = useState(() => initialServiceValues(service));
    const [imageFile, setImageFile] = useState<File | null>(null);
    const [savedImageUrl, setSavedImageUrl] = useState(service?.image_url ?? null);
    const [loadedServiceId, setLoadedServiceId] = useState(service?.id ?? null);

    if ((service?.id ?? null) !== loadedServiceId) {
        setLoadedServiceId(service?.id ?? null);
        setValues(initialServiceValues(service));
        setSavedImageUrl(service?.image_url ?? null);
        setImageFile(null);
    }

    const createService = useCreateService();
    const updateService = useUpdateService();
    const attachImage = useAttachServiceImage();
    const removeImage = useRemoveServiceImage();

    const update = useCallback(
        <TKey extends ServiceField>(key: TKey, value: ServiceFormValues[TKey]) => {
            setValues((current) => ({ ...current, [key]: value }));
            clearField(serverFields[key]);
        },
        [clearField],
    );

    const errorFor = useCallback(
        (field: ServiceField) => fieldErrors[serverFields[field]],
        [fieldErrors],
    );

    const clearImage = useCallback(() => {
        setImageFile(null);
        setSavedImageUrl(null);
    }, []);

    async function persist(): Promise<Service> {
        if (mode === 'edit' && service !== null) {
            return updateService.mutateAsync({
                id: service.id,
                payload: servicePayloadFrom(values),
            });
        }

        return createService.mutateAsync(servicePayloadFrom(values));
    }

    async function syncImage(saved: Service): Promise<boolean> {
        try {
            if (savedImageUrl === null && saved.image_url !== null) {
                await removeImage.mutateAsync(saved.id);
            }

            if (imageFile !== null) {
                await attachImage.mutateAsync({ id: saved.id, image: imageFile });
            }

            return true;
        } catch {
            return false;
        }
    }

    async function save() {
        reset();

        let saved: Service;

        try {
            saved = await persist();
        } catch (error) {
            capture(error, t('services.form.errors.unexpected'));

            return;
        }

        if (! (await syncImage(saved))) {
            raiseErrorToast(t('services.form.image.failed'));

            if (mode === 'create') {
                router.visit(serviceEditUrl(saved.id));
            }

            return;
        }

        raiseSuccessToast(
            mode === 'create' ? t('services.toasts.created') : t('services.toasts.saved'),
        );

        router.visit(SERVICES_URL);
    }

    return {
        values,
        update,
        errorFor,
        image: {
            file: imageFile,
            previewUrl: savedImageUrl,
            select: setImageFile,
            clear: clearImage,
        },
        isSubmitting:
            createService.isPending ||
            updateService.isPending ||
            attachImage.isPending ||
            removeImage.isPending,
        submit: (event: FormEvent<HTMLFormElement>) => {
            event.preventDefault();
            void save();
        },
    };
}

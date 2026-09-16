import { router } from '@inertiajs/react';
import { useCallback, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { useObjectUrl } from '@/hooks/use-object-url';
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
    shownUrl: string | null;
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
    const [isNavigating, setIsNavigating] = useState(false);

    if ((service?.id ?? null) !== loadedServiceId) {
        setLoadedServiceId(service?.id ?? null);
        setValues(initialServiceValues(service));
        setSavedImageUrl(service?.image_url ?? null);
        setImageFile(null);
    }

    const imageObjectUrl = useObjectUrl(imageFile);

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

    function navigateTo(url: string) {
        setIsNavigating(true);
        router.visit(url);
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
                navigateTo(serviceEditUrl(saved.id));
            }

            return;
        }

        raiseSuccessToast(
            mode === 'create' ? t('services.toasts.created') : t('services.toasts.saved'),
        );

        navigateTo(SERVICES_URL);
    }

    return {
        values,
        update,
        errorFor,
        image: {
            shownUrl: imageFile === null ? savedImageUrl : imageObjectUrl,
            select: setImageFile,
            clear: clearImage,
        },
        isSubmitting:
            isNavigating ||
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

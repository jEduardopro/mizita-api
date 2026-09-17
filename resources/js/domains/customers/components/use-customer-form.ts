import { router } from '@inertiajs/react';
import { useCallback, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { useAddressClearingGuard } from '@/hooks/use-address-clearing-guard';
import { useObjectUrl } from '@/hooks/use-object-url';
import { useServerErrors } from '@/hooks/use-server-errors';
import { raiseErrorToast, raiseSuccessToast } from '@/lib/toast';
import {
    useAttachCustomerPhoto,
    useCreateCustomer,
    useRemoveCustomerPhoto,
    useUpdateCustomer,
} from '../queries';
import type { Customer } from '../types';
import {
    customerPayloadFrom,
    initialCustomerValues,
    serverFields,
    type CustomerField,
    type CustomerFormValues,
} from './customer-form-values';
import { customerEditUrl, CUSTOMERS_URL } from './customer-urls';

export type CustomerFormMode = 'create' | 'edit';

type PhotoState = {
    shownUrl: string | null;
    select: (file: File) => void;
    clear: () => void;
};

export type CustomerFormController = {
    values: CustomerFormValues;
    update: <TKey extends CustomerField>(key: TKey, value: CustomerFormValues[TKey]) => void;
    errorFor: (field: CustomerField) => string | undefined;
    isRequired: (field: CustomerField) => boolean;
    photo: PhotoState;
    isSubmitting: boolean;
    submit: (event: FormEvent<HTMLFormElement>) => void;
};

type Params = {
    mode: CustomerFormMode;
    customer: Customer | null;
};

export function useCustomerForm({ mode, customer }: Params): CustomerFormController {
    const { t } = useTranslation('admin');
    const { fieldErrors, capture, clearField, reset } = useServerErrors();

    const [values, setValues] = useState(() => initialCustomerValues(customer));
    const [photoFile, setPhotoFile] = useState<File | null>(null);
    const [savedPhotoUrl, setSavedPhotoUrl] = useState(customer?.photo_url ?? null);
    const [loadedCustomerId, setLoadedCustomerId] = useState(customer?.id ?? null);
    const [isNavigating, setIsNavigating] = useState(false);

    if ((customer?.id ?? null) !== loadedCustomerId) {
        setLoadedCustomerId(customer?.id ?? null);
        setValues(initialCustomerValues(customer));
        setSavedPhotoUrl(customer?.photo_url ?? null);
        setPhotoFile(null);
    }

    const photoObjectUrl = useObjectUrl(photoFile);

    const addressGuard = useAddressClearingGuard({
        stored: customer?.address ?? null,
        values,
        fieldMessage: t('customers.form.address.cannotClear.field'),
        blockedMessage: t('customers.form.address.cannotClear.blocked'),
    });

    const createCustomer = useCreateCustomer();
    const updateCustomer = useUpdateCustomer();
    const attachPhoto = useAttachCustomerPhoto();
    const removePhoto = useRemoveCustomerPhoto();

    const update = useCallback(
        <TKey extends CustomerField>(key: TKey, value: CustomerFormValues[TKey]) => {
            setValues((current) => ({ ...current, [key]: value }));
            clearField(serverFields[key]);
        },
        [clearField],
    );

    const errorFor = useCallback(
        (field: CustomerField) => addressGuard.errorFor(field) ?? fieldErrors[serverFields[field]],
        [addressGuard, fieldErrors],
    );

    const clearPhoto = useCallback(() => {
        setPhotoFile(null);
        setSavedPhotoUrl(null);
    }, []);

    async function persist(): Promise<Customer> {
        if (mode === 'edit' && customer !== null) {
            return updateCustomer.mutateAsync({
                id: customer.id,
                payload: customerPayloadFrom(values),
            });
        }

        return createCustomer.mutateAsync(customerPayloadFrom(values));
    }

    async function syncPhoto(saved: Customer): Promise<boolean> {
        try {
            if (savedPhotoUrl === null && saved.photo_url !== null) {
                await removePhoto.mutateAsync(saved.id);
            }

            if (photoFile !== null) {
                await attachPhoto.mutateAsync({ id: saved.id, photo: photoFile });
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

        if (! addressGuard.confirmSaveAllowed()) {
            return;
        }

        let saved: Customer;

        try {
            saved = await persist();
        } catch (error) {
            addressGuard.captureRefusal(error);
            capture(error, t('customers.form.errors.unexpected'));

            return;
        }

        if (! (await syncPhoto(saved))) {
            raiseErrorToast(t('customers.form.photo.failed'));

            if (mode === 'create') {
                navigateTo(customerEditUrl(saved.id));
            }

            return;
        }

        raiseSuccessToast(
            mode === 'create' ? t('customers.toasts.created') : t('customers.toasts.saved'),
        );

        navigateTo(CUSTOMERS_URL);
    }

    return {
        values,
        update,
        errorFor,
        isRequired: addressGuard.isRequired,
        photo: {
            shownUrl: photoFile === null ? savedPhotoUrl : photoObjectUrl,
            select: setPhotoFile,
            clear: clearPhoto,
        },
        isSubmitting:
            isNavigating ||
            createCustomer.isPending ||
            updateCustomer.isPending ||
            attachPhoto.isPending ||
            removePhoto.isPending,
        submit: (event: FormEvent<HTMLFormElement>) => {
            event.preventDefault();
            void save();
        },
    };
}

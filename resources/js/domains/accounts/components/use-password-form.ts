import { useCallback, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { useServerErrors } from '@/hooks/use-server-errors';
import { raiseSuccessToast } from '@/lib/toast';
import { useUpdatePassword } from '../queries';
import type { UpdatePasswordPayload } from '../types';

export type PasswordField = 'currentPassword' | 'password' | 'passwordConfirmation';

type PasswordValues = Record<PasswordField, string>;

const EMPTY_VALUES: PasswordValues = {
    currentPassword: '',
    password: '',
    passwordConfirmation: '',
};

const serverFields: Record<PasswordField, string> = {
    currentPassword: 'current_password',
    password: 'password',
    passwordConfirmation: 'password_confirmation',
};

export type PasswordFormController = {
    values: PasswordValues;
    update: (field: PasswordField, value: string) => void;
    errorFor: (field: PasswordField) => string | undefined;
    requiresCurrentPassword: boolean;
    canSubmit: boolean;
    isSubmitting: boolean;
    submit: (event: FormEvent<HTMLFormElement>) => void;
};

type Params = {
    hasPassword: boolean;
    onSaved: () => void;
};

function payloadFrom(values: PasswordValues, hasPassword: boolean): UpdatePasswordPayload {
    const payload: UpdatePasswordPayload = {
        password: values.password,
        password_confirmation: values.passwordConfirmation,
    };

    return hasPassword ? { ...payload, current_password: values.currentPassword } : payload;
}

export function usePasswordForm({ hasPassword, onSaved }: Params): PasswordFormController {
    const { t } = useTranslation('admin');
    const { fieldErrors, capture, clearField, reset } = useServerErrors();
    const updatePassword = useUpdatePassword();
    const [values, setValues] = useState<PasswordValues>(EMPTY_VALUES);

    const update = useCallback(
        (field: PasswordField, value: string) => {
            setValues((current) => ({ ...current, [field]: value }));
            clearField(serverFields[field]);
        },
        [clearField],
    );

    const hasCurrentPassword = ! hasPassword || values.currentPassword !== '';

    async function save() {
        reset();

        try {
            await updatePassword.mutateAsync(payloadFrom(values, hasPassword));
        } catch (error) {
            capture(error, t('security.passwordForm.unexpected'));

            return;
        }

        setValues(EMPTY_VALUES);
        raiseSuccessToast(
            hasPassword ? t('security.passwordForm.updated') : t('security.passwordForm.created'),
        );
        onSaved();
    }

    return {
        values,
        update,
        errorFor: (field) => fieldErrors[serverFields[field]],
        requiresCurrentPassword: hasPassword,
        canSubmit: hasCurrentPassword && values.password !== '' && values.passwordConfirmation !== '',
        isSubmitting: updatePassword.isPending,
        submit: (event: FormEvent<HTMLFormElement>) => {
            event.preventDefault();
            void save();
        },
    };
}

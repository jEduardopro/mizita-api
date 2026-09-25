import { useCallback, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { useServerErrors } from '@/hooks/use-server-errors';
import { raiseSuccessToast } from '@/lib/toast';
import { useUpdatePassword } from '../queries';

export type ChangePasswordField = 'password' | 'passwordConfirmation';

type ChangePasswordValues = Record<ChangePasswordField, string>;

const EMPTY_VALUES: ChangePasswordValues = {
    password: '',
    passwordConfirmation: '',
};

const serverFields: Record<ChangePasswordField, string> = {
    password: 'password',
    passwordConfirmation: 'password_confirmation',
};

export type ChangePasswordFormController = {
    values: ChangePasswordValues;
    update: (field: ChangePasswordField, value: string) => void;
    errorFor: (field: ChangePasswordField) => string | undefined;
    canSubmit: boolean;
    isSubmitting: boolean;
    submit: (event: FormEvent<HTMLFormElement>) => void;
};

type Params = {
    onChanged: () => void;
};

export function useChangePasswordForm({ onChanged }: Params): ChangePasswordFormController {
    const { t } = useTranslation('auth');
    const { fieldErrors, capture, clearField, reset } = useServerErrors();
    const updatePassword = useUpdatePassword();
    const [values, setValues] = useState<ChangePasswordValues>(EMPTY_VALUES);

    const update = useCallback(
        (field: ChangePasswordField, value: string) => {
            setValues((current) => ({ ...current, [field]: value }));
            clearField(serverFields[field]);
        },
        [clearField],
    );

    async function save() {
        reset();

        try {
            await updatePassword.mutateAsync({
                password: values.password,
                password_confirmation: values.passwordConfirmation,
            });
        } catch (error) {
            capture(error, t('changePassword.unexpected'));

            return;
        }

        raiseSuccessToast(t('changePassword.saved'));
        onChanged();
    }

    return {
        values,
        update,
        errorFor: (field) => fieldErrors[serverFields[field]],
        canSubmit: values.password !== '' && values.passwordConfirmation !== '',
        isSubmitting: updatePassword.isPending,
        submit: (event: FormEvent<HTMLFormElement>) => {
            event.preventDefault();
            void save();
        },
    };
}

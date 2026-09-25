import { useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { useServerErrors } from '@/hooks/use-server-errors';
import { useConfirmPassword } from '../queries';

const PASSWORD_FIELD = 'password';

export type ConfirmPasswordFormController = {
    password: string;
    update: (value: string) => void;
    error: string | undefined;
    canSubmit: boolean;
    isSubmitting: boolean;
    submit: (event: FormEvent<HTMLFormElement>) => void;
};

type Params = {
    onConfirmed: () => void;
};

export function useConfirmPasswordForm({ onConfirmed }: Params): ConfirmPasswordFormController {
    const { t } = useTranslation('admin');
    const { fieldErrors, capture, clearField, reset } = useServerErrors();
    const confirmPassword = useConfirmPassword();
    const [password, setPassword] = useState('');

    function update(value: string) {
        setPassword(value);
        clearField(PASSWORD_FIELD);
    }

    async function confirm() {
        reset();

        try {
            await confirmPassword.mutateAsync({ password });
        } catch (error) {
            capture(error, t('security.confirmPassword.unexpected'));

            return;
        }

        onConfirmed();
    }

    return {
        password,
        update,
        error: fieldErrors[PASSWORD_FIELD],
        canSubmit: password !== '',
        isSubmitting: confirmPassword.isPending,
        submit: (event: FormEvent<HTMLFormElement>) => {
            event.preventDefault();
            void confirm();
        },
    };
}

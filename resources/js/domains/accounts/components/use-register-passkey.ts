import { useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { useErrorToast } from '@/hooks/use-error-toast';
import { useServerErrors } from '@/hooks/use-server-errors';
import { raiseSuccessToast } from '@/lib/toast';
import { isPasskeyAlreadyRegistered, isPasskeyCancellation } from '@/lib/webauthn';
import { useRegisterPasskey } from '../queries';
import { usePasswordConfirmation, type PasswordConfirmationDialogProps } from './use-password-confirmation';

const NAME_FIELD = 'name';

export type RegisterPasskeyFormController = {
    name: string;
    update: (value: string) => void;
    error: string | undefined;
    wasCancelled: boolean;
    canSubmit: boolean;
    isSubmitting: boolean;
    submit: (event: FormEvent<HTMLFormElement>) => void;
    passwordDialog: PasswordConfirmationDialogProps;
};

type Params = {
    onAdded: () => void;
};

export function useRegisterPasskeyForm({ onAdded }: Params): RegisterPasskeyFormController {
    const { t } = useTranslation('admin');
    const { fieldErrors, capture, clearField, reset } = useServerErrors();
    const deviceErrorToast = useErrorToast();
    const gate = usePasswordConfirmation();
    const registerPasskey = useRegisterPasskey();
    const [name, setName] = useState('');
    const [wasCancelled, setCancelled] = useState(false);

    function update(value: string) {
        setName(value);
        clearField(NAME_FIELD);
    }

    function handleFailure(error: unknown) {
        if (isPasskeyCancellation(error)) {
            setCancelled(true);

            return;
        }

        if (isPasskeyAlreadyRegistered(error)) {
            deviceErrorToast.show(t('security.passkey.alreadyRegistered'));

            return;
        }

        capture(error, t('security.passkey.addFailed'));
    }

    async function register() {
        reset();
        deviceErrorToast.dismiss();
        setCancelled(false);

        try {
            const outcome = await gate.confirmThen(() => registerPasskey.mutateAsync(name.trim()));

            if (outcome.status === 'cancelled') {
                return;
            }
        } catch (error) {
            handleFailure(error);

            return;
        }

        raiseSuccessToast(t('security.passkey.added'));
        onAdded();
    }

    return {
        name,
        update,
        error: fieldErrors[NAME_FIELD],
        wasCancelled,
        canSubmit: name.trim() !== '',
        isSubmitting: gate.isChecking || registerPasskey.isPending,
        submit: (event: FormEvent<HTMLFormElement>) => {
            event.preventDefault();
            void register();
        },
        passwordDialog: gate.dialog,
    };
}

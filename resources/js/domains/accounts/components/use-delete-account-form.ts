import { useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { useServerErrors } from '@/hooks/use-server-errors';
import { errorCodeFrom, formMessageFrom } from '@/lib/http';
import { useDeleteAccount } from '../queries';
import {
    ACCOUNT_DELETION_EMAIL_MISMATCH_CODE,
    ACCOUNT_HAS_UPCOMING_APPOINTMENTS_CODE,
    INCORRECT_ACCOUNT_PASSWORD_CODE,
    type DeleteAccountPayload,
} from '../types';

const LANDING_URL = '/';

const CONFIRMATION_REFUSAL_CODES: readonly string[] = [
    INCORRECT_ACCOUNT_PASSWORD_CODE,
    ACCOUNT_DELETION_EMAIL_MISMATCH_CODE,
];

type ConfirmationField = keyof DeleteAccountPayload;

export type DeleteAccountFormController = {
    confirmation: string;
    update: (value: string) => void;
    error: string | undefined;
    wasBlocked: boolean;
    canSubmit: boolean;
    isSubmitting: boolean;
    submit: (event: FormEvent<HTMLFormElement>) => void;
};

type Params = {
    hasPassword: boolean;
};

function payloadFrom(confirmation: string, hasPassword: boolean): DeleteAccountPayload {
    return hasPassword ? { password: confirmation } : { email: confirmation.trim() };
}

function isConfirmationRefusal(error: unknown): boolean {
    const code = errorCodeFrom(error);

    return code !== undefined && CONFIRMATION_REFUSAL_CODES.includes(code);
}

export function useDeleteAccountForm({ hasPassword }: Params): DeleteAccountFormController {
    const { t } = useTranslation('admin');
    const { fieldErrors, capture, clearField, reset } = useServerErrors();
    const deleteAccount = useDeleteAccount();
    const [confirmation, setConfirmation] = useState('');
    const [refusal, setRefusal] = useState<string | undefined>(undefined);
    const [wasBlocked, setWasBlocked] = useState(false);
    const field: ConfirmationField = hasPassword ? 'password' : 'email';
    const unexpectedMessage = t('security.account.delete.unexpected');

    function update(value: string) {
        setConfirmation(value);
        setRefusal(undefined);
        clearField(field);
    }

    function handleFailure(error: unknown) {
        if (errorCodeFrom(error) === ACCOUNT_HAS_UPCOMING_APPOINTMENTS_CODE) {
            setWasBlocked(true);

            return;
        }

        if (isConfirmationRefusal(error)) {
            setRefusal(formMessageFrom(error, unexpectedMessage));

            return;
        }

        capture(error, unexpectedMessage);
    }

    async function confirm() {
        reset();
        setRefusal(undefined);

        try {
            await deleteAccount.mutateAsync(payloadFrom(confirmation, hasPassword));
        } catch (error) {
            handleFailure(error);

            return;
        }

        window.location.replace(LANDING_URL);
    }

    return {
        confirmation,
        update,
        error: refusal ?? fieldErrors[field],
        wasBlocked,
        canSubmit: confirmation.trim() !== '',
        isSubmitting: deleteAccount.isPending || deleteAccount.isSuccess,
        submit: (event: FormEvent<HTMLFormElement>) => {
            event.preventDefault();
            void confirm();
        },
    };
}

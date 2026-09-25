import { useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { OTP_LENGTH } from '@/components/form/OtpField';
import { useCopyToClipboard } from '@/hooks/use-copy-to-clipboard';
import { useServerErrors } from '@/hooks/use-server-errors';
import { raiseSuccessToast } from '@/lib/toast';
import { useConfirmTwoFactor, useDisableTwoFactor, useTwoFactorQrCode, useTwoFactorSecretKey } from '../queries';
import { usePasswordConfirmation, type PasswordConfirmationDialogProps } from './use-password-confirmation';

const CODE_FIELD = 'code';

const AUTHENTICATOR_URL_SCHEME = 'otpauth://';

export type TwoFactorSetupStep = 'verify' | 'recoveryCodes';

type SetupAction = 'confirm' | 'cancel';

export type SetupMaterial =
    | { status: 'loading' }
    | { status: 'failed'; isRetrying: boolean }
    | { status: 'ready'; qrCodeSvg: string; authenticatorUrl: string | null; secretKey: string };

export type TwoFactorSetupController = {
    step: TwoFactorSetupStep;
    material: SetupMaterial;
    retryMaterial: () => void;
    copySecretKey: () => void;
    code: string;
    updateCode: (value: string) => void;
    codeError: string | undefined;
    canSubmit: boolean;
    isBusy: boolean;
    isConfirming: boolean;
    isCancelling: boolean;
    submit: (event: FormEvent<HTMLFormElement>) => void;
    submitCode: (value: string) => void;
    cancel: () => void;
    passwordDialog: PasswordConfirmationDialogProps;
};

type Params = {
    onCancelled: () => void;
};

function launchableAuthenticatorUrl(url: string): string | null {
    return url.startsWith(AUTHENTICATOR_URL_SCHEME) ? url : null;
}

export function useTwoFactorSetup({ onCancelled }: Params): TwoFactorSetupController {
    const { t } = useTranslation('admin');
    const gate = usePasswordConfirmation();
    const { fieldErrors, capture, clearField, reset } = useServerErrors();
    const confirmTwoFactor = useConfirmTwoFactor();
    const disableTwoFactor = useDisableTwoFactor();
    const [step, setStep] = useState<TwoFactorSetupStep>('verify');
    const [code, setCode] = useState('');
    const [runningAction, setRunningAction] = useState<SetupAction | null>(null);

    const isMaterialNeeded = step === 'verify' && runningAction !== 'cancel';
    const qrCode = useTwoFactorQrCode({ enabled: isMaterialNeeded });
    const secretKey = useTwoFactorSecretKey({ enabled: isMaterialNeeded });

    const copy = useCopyToClipboard({
        copied: t('security.twoFactor.copied'),
        failed: t('security.twoFactor.copyFailed'),
    });

    function materialOf(): SetupMaterial {
        if (qrCode.data !== undefined && secretKey.data !== undefined) {
            return {
                status: 'ready',
                qrCodeSvg: qrCode.data.svg,
                authenticatorUrl: launchableAuthenticatorUrl(qrCode.data.url),
                secretKey: secretKey.data,
            };
        }

        if (qrCode.isError || secretKey.isError) {
            return { status: 'failed', isRetrying: qrCode.isFetching || secretKey.isFetching };
        }

        return { status: 'loading' };
    }

    function retryMaterial() {
        void gate
            .confirmThen(() =>
                Promise.all([qrCode.refetch({ throwOnError: true }), secretKey.refetch({ throwOnError: true })]),
            )
            .catch(() => undefined);
    }

    function updateCode(value: string) {
        setCode(value);
        clearField(CODE_FIELD);
    }

    async function confirm(value: string) {
        if (runningAction !== null || value.length !== OTP_LENGTH) {
            return;
        }

        reset();
        setRunningAction('confirm');

        try {
            const outcome = await gate.confirmThen(() => confirmTwoFactor.mutateAsync({ code: value }));

            if (outcome.status === 'completed') {
                raiseSuccessToast(t('security.twoFactor.activated'));
                setStep('recoveryCodes');
            }
        } catch (error) {
            capture(error, t('security.twoFactor.confirmFailed'));
            setCode('');
        } finally {
            setRunningAction(null);
        }
    }

    async function cancel() {
        if (runningAction !== null) {
            return;
        }

        reset();
        setRunningAction('cancel');

        try {
            const outcome = await gate.confirmThen(() => disableTwoFactor.mutateAsync());

            if (outcome.status === 'completed') {
                onCancelled();
            }
        } catch (error) {
            capture(error, t('security.twoFactor.disableFailed'));
        } finally {
            setRunningAction(null);
        }
    }

    return {
        step,
        material: materialOf(),
        retryMaterial,
        copySecretKey: () => {
            if (secretKey.data !== undefined) {
                copy(secretKey.data);
            }
        },
        code,
        updateCode,
        codeError: fieldErrors[CODE_FIELD],
        canSubmit: code.length === OTP_LENGTH,
        isBusy: runningAction !== null,
        isConfirming: runningAction === 'confirm',
        isCancelling: runningAction === 'cancel',
        submit: (event: FormEvent<HTMLFormElement>) => {
            event.preventDefault();
            void confirm(code);
        },
        submitCode: (value: string) => void confirm(value),
        cancel: () => void cancel(),
        passwordDialog: gate.dialog,
    };
}

import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useCopyToClipboard } from '@/hooks/use-copy-to-clipboard';
import { useErrorToast } from '@/hooks/use-error-toast';
import { formMessageFrom } from '@/lib/http';
import { raiseSuccessToast } from '@/lib/toast';
import { useRegenerateRecoveryCodes, useTwoFactorRecoveryCodes } from '../queries';
import { usePasswordConfirmation, type PasswordConfirmationDialogProps } from './use-password-confirmation';

const PLAIN_TEXT_FILE_TYPE = 'text/plain;charset=utf-8';

const OBJECT_URL_REVOKE_DELAY_MS = 1_000;

const LINE_BREAK = '\n';

export type RecoveryCodesState =
    | { status: 'loading' }
    | { status: 'failed'; isRetrying: boolean }
    | { status: 'ready'; codes: string[] };

export type RecoveryCodesController = {
    state: RecoveryCodesState;
    retry: () => void;
    copyAll: () => void;
    download: () => void;
    isRegenerateDialogOpen: boolean;
    setRegenerateDialogOpen: (open: boolean) => void;
    regenerate: () => void;
    isRegenerating: boolean;
    passwordDialog: PasswordConfirmationDialogProps;
};

function downloadTextFile(fileName: string, contents: string): void {
    const url = URL.createObjectURL(new Blob([contents], { type: PLAIN_TEXT_FILE_TYPE }));
    const link = document.createElement('a');

    link.href = url;
    link.download = fileName;
    link.click();

    window.setTimeout(() => URL.revokeObjectURL(url), OBJECT_URL_REVOKE_DELAY_MS);
}

function asTextFile(codes: string[]): string {
    return codes.join(LINE_BREAK) + LINE_BREAK;
}

export function useRecoveryCodes(): RecoveryCodesController {
    const { t } = useTranslation('admin');
    const gate = usePasswordConfirmation();
    const recoveryCodes = useTwoFactorRecoveryCodes({ enabled: true });
    const regeneration = useRegenerateRecoveryCodes();
    const errorToast = useErrorToast();
    const [isRegenerateDialogOpen, setRegenerateDialogOpen] = useState(false);

    const copy = useCopyToClipboard({
        copied: t('security.twoFactor.recoveryCodes.copied'),
        failed: t('security.twoFactor.recoveryCodes.copyFailed'),
    });

    const codes = recoveryCodes.data;

    function stateOf(): RecoveryCodesState {
        if (codes !== undefined) {
            return { status: 'ready', codes };
        }

        if (recoveryCodes.isError) {
            return { status: 'failed', isRetrying: recoveryCodes.isFetching };
        }

        return { status: 'loading' };
    }

    function retry() {
        void gate.confirmThen(() => recoveryCodes.refetch({ throwOnError: true })).catch(() => undefined);
    }

    async function regenerate() {
        errorToast.dismiss();

        try {
            const outcome = await gate.confirmThen(() => regeneration.mutateAsync());

            if (outcome.status === 'completed') {
                raiseSuccessToast(t('security.twoFactor.recoveryCodes.regenerated'));
            }
        } catch (error) {
            errorToast.show(formMessageFrom(error, t('security.twoFactor.recoveryCodes.regenerateFailed')));
        }
    }

    return {
        state: stateOf(),
        retry,
        copyAll: () => {
            if (codes !== undefined) {
                copy(asTextFile(codes));
            }
        },
        download: () => {
            if (codes !== undefined) {
                downloadTextFile(t('security.twoFactor.recoveryCodes.fileName'), asTextFile(codes));
            }
        },
        isRegenerateDialogOpen,
        setRegenerateDialogOpen,
        regenerate: () => void regenerate(),
        isRegenerating: regeneration.isPending,
        passwordDialog: gate.dialog,
    };
}

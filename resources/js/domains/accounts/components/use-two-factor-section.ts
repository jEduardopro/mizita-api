import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useErrorToast } from '@/hooks/use-error-toast';
import { formMessageFrom } from '@/lib/http';
import { raiseSuccessToast } from '@/lib/toast';
import { useDisableTwoFactor, useEnableTwoFactor } from '../queries';
import { usePasswordConfirmation, type PasswordConfirmationDialogProps } from './use-password-confirmation';

type SectionAction = 'startSetup' | 'openRecoveryCodes' | 'disable';

type Params = {
    onSetupStarted: () => void;
    onOpenRecoveryCodes: () => void;
    onDisabled: () => void;
};

export type TwoFactorSectionController = {
    isBusy: boolean;
    isStartingSetup: boolean;
    isOpeningRecoveryCodes: boolean;
    isDisabling: boolean;
    startSetup: () => void;
    openRecoveryCodes: () => void;
    disable: () => void;
    passwordDialog: PasswordConfirmationDialogProps;
};

const confirmationOnly = () => Promise.resolve();

export function useTwoFactorSection({
    onSetupStarted,
    onOpenRecoveryCodes,
    onDisabled,
}: Params): TwoFactorSectionController {
    const { t } = useTranslation('admin');
    const gate = usePasswordConfirmation();
    const enableTwoFactor = useEnableTwoFactor();
    const disableTwoFactor = useDisableTwoFactor();
    const errorToast = useErrorToast();
    const [runningAction, setRunningAction] = useState<SectionAction | null>(null);

    async function run(action: SectionAction, task: () => Promise<unknown>, failureMessage: string): Promise<boolean> {
        errorToast.dismiss();
        setRunningAction(action);

        try {
            const outcome = await gate.confirmThen(task);

            return outcome.status === 'completed';
        } catch (error) {
            errorToast.show(formMessageFrom(error, failureMessage));

            return false;
        } finally {
            setRunningAction(null);
        }
    }

    async function startSetup() {
        const started = await run(
            'startSetup',
            () => enableTwoFactor.mutateAsync(),
            t('security.twoFactor.enableFailed'),
        );

        if (started) {
            onSetupStarted();
        }
    }

    async function openRecoveryCodes() {
        const confirmed = await run(
            'openRecoveryCodes',
            confirmationOnly,
            t('security.twoFactor.recoveryCodes.loadFailed'),
        );

        if (confirmed) {
            onOpenRecoveryCodes();
        }
    }

    async function disable() {
        const disabled = await run(
            'disable',
            () => disableTwoFactor.mutateAsync(),
            t('security.twoFactor.disableFailed'),
        );

        if (disabled) {
            raiseSuccessToast(t('security.twoFactor.deactivated'));
            onDisabled();
        }
    }

    return {
        isBusy: runningAction !== null,
        isStartingSetup: runningAction === 'startSetup',
        isOpeningRecoveryCodes: runningAction === 'openRecoveryCodes',
        isDisabling: runningAction === 'disable',
        startSetup: () => void startSetup(),
        openRecoveryCodes: () => void openRecoveryCodes(),
        disable: () => void disable(),
        passwordDialog: gate.dialog,
    };
}

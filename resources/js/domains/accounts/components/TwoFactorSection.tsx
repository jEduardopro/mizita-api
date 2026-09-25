import { cn } from 'cn';
import { ShieldOff } from 'lucide-react';
import { useId, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { SubmitButton } from '@/components/form/SubmitButton';
import type { TwoFactorStatus } from '../types';
import { ConfirmPasswordDialog } from './ConfirmPasswordDialog';
import { PasswordRequiredNotice } from './PasswordRequiredNotice';
import { SecurityConfirmDialog } from './SecurityConfirmDialog';
import { TwoFactorStatusBadge } from './TwoFactorStatusBadge';
import { useTwoFactorSection, type TwoFactorSectionController } from './use-two-factor-section';

const ACTION_BUTTON_CLASSES = 'h-11 rounded-full px-5 md:h-9';

const STATUS_BODY_KEYS = {
    disabled: 'security.twoFactor.body',
    pending: 'security.twoFactor.pendingBody',
    enabled: 'security.twoFactor.enabledBody',
} as const satisfies Record<TwoFactorStatus, string>;

type EnabledActionsProps = {
    section: TwoFactorSectionController;
};

function EnabledActions({ section }: EnabledActionsProps) {
    const { t } = useTranslation('admin');
    const [isDisableDialogOpen, setDisableDialogOpen] = useState(false);

    return (
        <div className="mt-1 flex flex-wrap gap-2">
            <SubmitButton
                type="button"
                variant="outline"
                onClick={section.openRecoveryCodes}
                disabled={section.isBusy}
                isSubmitting={section.isOpeningRecoveryCodes}
                label={t('security.twoFactor.recoveryCodes.show')}
                submittingLabel={t('security.twoFactor.recoveryCodes.show')}
                className={ACTION_BUTTON_CLASSES}
            />

            <SubmitButton
                type="button"
                variant="ghost"
                onClick={() => setDisableDialogOpen(true)}
                disabled={section.isBusy}
                isSubmitting={section.isDisabling}
                label={t('security.twoFactor.disable')}
                submittingLabel={t('security.twoFactor.disabling')}
                className={cn(ACTION_BUTTON_CLASSES, 'text-destructive hover:bg-destructive/10 hover:text-destructive')}
            />

            <SecurityConfirmDialog
                open={isDisableDialogOpen}
                onOpenChange={setDisableDialogOpen}
                icon={ShieldOff}
                title={t('security.twoFactor.disableConfirm.title')}
                body={t('security.twoFactor.disableConfirm.body')}
                actionLabel={t('security.twoFactor.disableConfirm.action')}
                onConfirm={section.disable}
            />
        </div>
    );
}

type SetupActionProps = {
    status: Exclude<TwoFactorStatus, 'enabled'>;
    section: TwoFactorSectionController;
};

function SetupAction({ status, section }: SetupActionProps) {
    const { t } = useTranslation('admin');
    const label = status === 'pending' ? t('security.twoFactor.resume') : t('security.twoFactor.enable');

    return (
        <SubmitButton
            type="button"
            variant="outline"
            onClick={section.startSetup}
            disabled={section.isBusy}
            isSubmitting={section.isStartingSetup}
            label={label}
            submittingLabel={t('security.twoFactor.enabling')}
            className={cn('mt-1', ACTION_BUTTON_CLASSES)}
        />
    );
}

type TwoFactorActionProps = {
    status: TwoFactorStatus;
    hasPassword: boolean;
    onCreatePassword: () => void;
    section: TwoFactorSectionController;
};

function TwoFactorAction({ status, hasPassword, onCreatePassword, section }: TwoFactorActionProps) {
    if (! hasPassword) {
        return (
            <div className="mt-1 w-full">
                <PasswordRequiredNotice onCreatePassword={onCreatePassword} />
            </div>
        );
    }

    if (status === 'enabled') {
        return <EnabledActions section={section} />;
    }

    return <SetupAction status={status} section={section} />;
}

type Props = {
    status: TwoFactorStatus;
    hasPassword: boolean;
    onCreatePassword: () => void;
    onSetupStarted: () => void;
    onOpenRecoveryCodes: () => void;
};

export function TwoFactorSection({ status, hasPassword, onCreatePassword, onSetupStarted, onOpenRecoveryCodes }: Props) {
    const { t } = useTranslation('admin');
    const headingId = useId();
    const headingRef = useRef<HTMLHeadingElement>(null);
    const section = useTwoFactorSection({
        onSetupStarted,
        onOpenRecoveryCodes,
        onDisabled: () => headingRef.current?.focus({ preventScroll: true }),
    });

    return (
        <section aria-labelledby={headingId} className="grid justify-items-start gap-2 py-5 first:pt-1">
            <div className="flex flex-wrap items-center gap-2">
                <h4 ref={headingRef} id={headingId} tabIndex={-1} className="text-sm font-semibold outline-none">
                    {t('security.twoFactor.title')}
                </h4>

                <span aria-live="polite" aria-atomic="true">
                    <TwoFactorStatusBadge status={status} />
                </span>
            </div>

            <p className="text-sm text-pretty text-muted-foreground">{t(STATUS_BODY_KEYS[status])}</p>

            <TwoFactorAction
                status={status}
                hasPassword={hasPassword}
                onCreatePassword={onCreatePassword}
                section={section}
            />

            <ConfirmPasswordDialog {...section.passwordDialog} />
        </section>
    );
}

import { cn } from 'cn';
import { Copy, Download, RefreshCw } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { SubmitButton } from '@/components/form/SubmitButton';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { ConfirmPasswordDialog } from './ConfirmPasswordDialog';
import { SecurityConfirmDialog } from './SecurityConfirmDialog';
import { useRecoveryCodes, type RecoveryCodesController } from './use-recovery-codes';

const ACTION_BUTTON_CLASSES = 'h-11 rounded-full px-5 md:h-9';

const SLIP_CLASSES =
    'grid gap-x-6 gap-y-2.5 rounded-lg border border-dashed border-foreground/20 bg-muted/60 px-4 py-4 sm:grid-cols-2 sm:px-5';

const SKELETON_ROWS = ['first', 'second', 'third', 'fourth', 'fifth', 'sixth', 'seventh', 'eighth'] as const;

function RecoveryCodesSkeleton() {
    const { t } = useTranslation('admin');

    return (
        <div aria-busy="true" className={SLIP_CLASSES}>
            <p role="status" className="sr-only">
                {t('security.twoFactor.recoveryCodes.loading')}
            </p>

            {SKELETON_ROWS.map((row) => (
                <Skeleton key={row} className="h-5 w-full max-w-48" />
            ))}
        </div>
    );
}

type RecoveryCodesFailedProps = {
    isRetrying: boolean;
    onRetry: () => void;
};

function RecoveryCodesFailed({ isRetrying, onRetry }: RecoveryCodesFailedProps) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');

    return (
        <div role="alert" className="grid justify-items-start gap-3 rounded-lg bg-muted px-4 py-4">
            <p className="text-sm text-pretty text-foreground/80">{t('security.twoFactor.recoveryCodes.loadFailed')}</p>

            <Button
                type="button"
                variant="outline"
                onClick={onRetry}
                disabled={isRetrying}
                aria-busy={isRetrying}
                className={ACTION_BUTTON_CLASSES}
            >
                {isRetrying ? tCommon('actions.retrying') : tCommon('actions.tryAgain')}
            </Button>
        </div>
    );
}

type RecoveryCodeSlipProps = {
    codes: string[];
    isRegenerating: boolean;
};

function RecoveryCodeSlip({ codes, isRegenerating }: RecoveryCodeSlipProps) {
    const { t } = useTranslation('admin');

    return (
        <ul
            aria-label={t('security.twoFactor.recoveryCodes.title')}
            aria-busy={isRegenerating}
            className={cn(SLIP_CLASSES, 'motion-safe:transition-opacity aria-busy:opacity-50')}
        >
            {codes.map((code) => (
                <li key={code} className="font-mono text-sm tracking-wide text-foreground tabular-nums wrap-anywhere">
                    {code}
                </li>
            ))}
        </ul>
    );
}

type RecoveryCodesActionsProps = {
    recovery: RecoveryCodesController;
};

function RecoveryCodesActions({ recovery }: RecoveryCodesActionsProps) {
    const { t } = useTranslation('admin');

    return (
        <div className="grid gap-4">
            <div className="flex flex-wrap gap-2">
                <Button type="button" variant="outline" onClick={recovery.copyAll} className={ACTION_BUTTON_CLASSES}>
                    <Copy aria-hidden="true" />
                    {t('security.twoFactor.recoveryCodes.copy')}
                </Button>

                <Button type="button" variant="outline" onClick={recovery.download} className={ACTION_BUTTON_CLASSES}>
                    <Download aria-hidden="true" />
                    {t('security.twoFactor.recoveryCodes.download')}
                </Button>
            </div>

            <div className="border-t border-border pt-4">
                <SubmitButton
                    type="button"
                    variant="ghost"
                    onClick={() => recovery.setRegenerateDialogOpen(true)}
                    isSubmitting={recovery.isRegenerating}
                    label={t('security.twoFactor.recoveryCodes.regenerate')}
                    submittingLabel={t('security.twoFactor.recoveryCodes.regenerating')}
                    className="-ml-3 h-11 px-3 md:h-9"
                />
            </div>
        </div>
    );
}

function RecoveryCodesContent({ recovery }: RecoveryCodesActionsProps) {
    const { state } = recovery;

    if (state.status === 'loading') {
        return <RecoveryCodesSkeleton />;
    }

    if (state.status === 'failed') {
        return <RecoveryCodesFailed isRetrying={state.isRetrying} onRetry={recovery.retry} />;
    }

    return (
        <>
            <RecoveryCodeSlip codes={state.codes} isRegenerating={recovery.isRegenerating} />
            <RecoveryCodesActions recovery={recovery} />
        </>
    );
}

export function RecoveryCodesPanel() {
    const { t } = useTranslation('admin');
    const recovery = useRecoveryCodes();

    return (
        <div className="grid gap-4">
            <p className="text-sm text-pretty text-muted-foreground">{t('security.twoFactor.recoveryCodes.body')}</p>

            <RecoveryCodesContent recovery={recovery} />

            <SecurityConfirmDialog
                open={recovery.isRegenerateDialogOpen}
                onOpenChange={recovery.setRegenerateDialogOpen}
                icon={RefreshCw}
                title={t('security.twoFactor.recoveryCodes.regenerateConfirm.title')}
                body={t('security.twoFactor.recoveryCodes.regenerateConfirm.body')}
                actionLabel={t('security.twoFactor.recoveryCodes.regenerateConfirm.action')}
                onConfirm={recovery.regenerate}
            />

            <ConfirmPasswordDialog {...recovery.passwordDialog} />
        </div>
    );
}

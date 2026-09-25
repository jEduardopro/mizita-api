import { useTranslation } from 'react-i18next';
import { SubmitButton } from '@/components/form/SubmitButton';
import { Skeleton } from '@/components/ui/skeleton';
import type { AccountReactivationStatus } from '../types';
import { DeletionDeadlineLeaf } from './DeletionDeadlineLeaf';
import { ReactivationOutcome } from './ReactivationOutcome';
import type { ReactivationAction } from './use-account-reactivation-flow';

const DEADLINE_FORMAT: Intl.DateTimeFormatOptions = {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
};

const ACTION_BUTTON = 'h-12 w-full rounded-xl text-sm';

type Props = {
    status: AccountReactivationStatus | undefined;
    hasFailed: boolean;
    isRetrying: boolean;
    onRetry: () => void;
    onReactivate: () => void;
    onDismiss: () => void;
    pendingAction: ReactivationAction | null;
};

export function ReactivateAccountPanel({ status, hasFailed, isRetrying, onRetry, ...actions }: Props) {
    if (hasFailed) {
        return <ReactivationLoadError isRetrying={isRetrying} onRetry={onRetry} />;
    }

    if (status === undefined) {
        return <ReactivationSkeleton />;
    }

    return <ReactivationOffer status={status} {...actions} />;
}

type OfferProps = {
    status: AccountReactivationStatus;
    onReactivate: () => void;
    onDismiss: () => void;
    pendingAction: ReactivationAction | null;
};

function ReactivationOffer({ status, onReactivate, onDismiss, pendingAction }: OfferProps) {
    const { t, i18n } = useTranslation('auth');
    const deadline = new Date(status.grace_period_ends_at);
    const isBusy = pendingAction !== null;

    return (
        <div className="grid gap-6">
            <section className="overflow-hidden rounded-2xl border border-border bg-card text-card-foreground">
                <div className="flex items-start gap-4 p-4">
                    <DeletionDeadlineLeaf deadline={deadline} locale={i18n.language} />

                    <div className="grid min-w-0 gap-1 pt-0.5">
                        <p className="text-pretty text-sm leading-relaxed">
                            {t('reactivateAccount.scheduled', {
                                name: status.name,
                                date: new Intl.DateTimeFormat(i18n.language, DEADLINE_FORMAT).format(deadline),
                            })}
                        </p>
                        <p className="truncate text-xs text-muted-foreground">{status.email}</p>
                    </div>
                </div>

                <ReactivationOutcome business={status.business} />
            </section>

            <div className="grid gap-2">
                <SubmitButton
                    type="button"
                    variant="brand"
                    size="lg"
                    className={ACTION_BUTTON}
                    label={t('reactivateAccount.reactivate')}
                    submittingLabel={t('reactivateAccount.reactivating')}
                    isSubmitting={pendingAction === 'reactivate'}
                    disabled={isBusy}
                    onClick={onReactivate}
                />

                <SubmitButton
                    type="button"
                    variant="ghost"
                    size="lg"
                    className={ACTION_BUTTON}
                    label={t('reactivateAccount.dismiss')}
                    submittingLabel={t('reactivateAccount.dismissing')}
                    isSubmitting={pendingAction === 'dismiss'}
                    disabled={isBusy}
                    onClick={onDismiss}
                />
            </div>

            <p className="text-pretty text-center text-xs text-muted-foreground">
                {t('reactivateAccount.dismissHint')}
            </p>
        </div>
    );
}

function ReactivationSkeleton() {
    const { t } = useTranslation('auth');

    return (
        <div role="status" aria-live="polite" className="grid gap-6">
            <span className="sr-only">{t('reactivateAccount.loading')}</span>

            <div aria-hidden="true" className="flex items-start gap-4 rounded-2xl border border-border p-4">
                <Skeleton className="h-[4.75rem] w-16 shrink-0 rounded-xl" />
                <div className="grid flex-1 gap-2 pt-1">
                    <Skeleton className="h-4 w-full" />
                    <Skeleton className="h-4 w-3/4" />
                    <Skeleton className="h-3 w-1/2" />
                </div>
            </div>

            <div aria-hidden="true" className="grid gap-2">
                <Skeleton className="h-12 w-full rounded-xl" />
                <Skeleton className="h-12 w-full rounded-xl" />
            </div>
        </div>
    );
}

type LoadErrorProps = {
    isRetrying: boolean;
    onRetry: () => void;
};

function ReactivationLoadError({ isRetrying, onRetry }: LoadErrorProps) {
    const { t } = useTranslation('auth');

    return (
        <div role="alert" className="grid gap-4 rounded-2xl border border-border p-4">
            <p className="text-pretty text-sm">{t('reactivateAccount.loadFailed.message')}</p>

            <SubmitButton
                type="button"
                variant="outline"
                size="lg"
                className={ACTION_BUTTON}
                label={t('reactivateAccount.loadFailed.retry')}
                submittingLabel={t('reactivateAccount.loadFailed.retrying')}
                isSubmitting={isRetrying}
                onClick={onRetry}
            />
        </div>
    );
}

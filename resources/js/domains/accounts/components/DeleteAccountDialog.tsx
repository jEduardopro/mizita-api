import { cn } from 'cn';
import { CalendarClock, CalendarX2, RotateCcw, Store, Trash2, TriangleAlert, UserX, Users, type LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { fullDateFormatter } from '@/components/form/date-format';
import { FormField } from '@/components/form/FormField';
import { SubmitButton } from '@/components/form/SubmitButton';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogMedia,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useAccountDeletionPreview } from '../queries';
import type { AccountDeletionPreview } from '../types';
import { useDeleteAccountForm } from './use-delete-account-form';

const CONTENT_CLASSES =
    'top-auto bottom-0 flex max-h-[min(92svh,46rem)] translate-y-0 flex-col gap-0 overflow-hidden rounded-b-none p-0 data-[size=default]:max-w-none sm:top-1/2 sm:bottom-auto sm:-translate-y-1/2 sm:rounded-b-xl data-[size=default]:sm:max-w-md';

const BODY_CLASSES = 'grid min-h-0 flex-1 content-start gap-5 overflow-y-auto overscroll-contain p-4 sm:p-5';

const FOOTER_CLASSES =
    'mx-0 mb-0 shrink-0 rounded-b-none px-4 pt-4 pb-[calc(1rem+env(safe-area-inset-bottom))] sm:rounded-b-xl sm:px-5 sm:pb-4';

const FOOTER_BUTTON_CLASSES = 'h-11 px-4 md:h-9';

const SKELETON_ROWS = ['closing', 'team', 'restore'] as const;

type ConsequenceTone = 'critical' | 'muted' | 'reassuring';

const TONE_CLASSES: Record<ConsequenceTone, string> = {
    critical: 'text-destructive',
    muted: 'text-muted-foreground',
    reassuring: 'text-success',
};

type ConsequenceProps = {
    icon: LucideIcon;
    tone: ConsequenceTone;
    children: ReactNode;
};

function Consequence({ icon: Icon, tone, children }: ConsequenceProps) {
    return (
        <li className="flex gap-3 text-sm text-foreground">
            <span className="grid size-8 shrink-0 place-items-center rounded-md bg-muted">
                <Icon aria-hidden="true" className={cn('size-4', TONE_CLASSES[tone])} />
            </span>

            <span className="min-w-0 self-center leading-snug text-pretty">{children}</span>
        </li>
    );
}

type OwnerConsequencesProps = {
    businessName: string;
    upcomingAppointmentsCount: number;
    graceDate: string;
};

function OwnerConsequences({ businessName, upcomingAppointmentsCount, graceDate }: OwnerConsequencesProps) {
    const { t } = useTranslation('admin');

    return (
        <ul className="grid gap-3">
            <Consequence icon={Store} tone="critical">
                {t('security.account.delete.owner.closed', { business: businessName })}
            </Consequence>

            <Consequence icon={Users} tone="critical">
                {t('security.account.delete.owner.team')}
            </Consequence>

            {upcomingAppointmentsCount > 0 ? (
                <Consequence icon={CalendarX2} tone="critical">
                    {t('security.account.delete.owner.appointments', { count: upcomingAppointmentsCount })}
                </Consequence>
            ) : null}

            <Consequence icon={RotateCcw} tone="reassuring">
                {t('security.account.delete.owner.restore', { date: graceDate })}
            </Consequence>

            <Consequence icon={Trash2} tone="muted">
                {t('security.account.delete.owner.purge', { date: graceDate })}
            </Consequence>
        </ul>
    );
}

function AccountConsequences({ graceDate }: { graceDate: string }) {
    const { t } = useTranslation('admin');

    return (
        <ul className="grid gap-3">
            <Consequence icon={UserX} tone="critical">
                {t('security.account.delete.regular.deleted')}
            </Consequence>

            <Consequence icon={RotateCcw} tone="reassuring">
                {t('security.account.delete.regular.restore', { date: graceDate })}
            </Consequence>
        </ul>
    );
}

type HeaderProps = {
    icon: LucideIcon;
    title: string;
    description: string;
};

function DialogHeader({ icon: Icon, title, description }: HeaderProps) {
    return (
        <AlertDialogHeader>
            <AlertDialogMedia>
                <Icon aria-hidden="true" className="text-destructive" />
            </AlertDialogMedia>

            <AlertDialogTitle className="wrap-anywhere">{title}</AlertDialogTitle>

            <AlertDialogDescription>{description}</AlertDialogDescription>
        </AlertDialogHeader>
    );
}

function CancelButton({ label, disabled }: { label: string; disabled?: boolean }) {
    return (
        <AlertDialogCancel disabled={disabled} className={FOOTER_BUTTON_CLASSES}>
            {label}
        </AlertDialogCancel>
    );
}

function PreviewLoading() {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');

    return (
        <>
            <div aria-busy="true" className={BODY_CLASSES}>
                <DialogHeader
                    icon={TriangleAlert}
                    title={t('security.account.delete.dialog.title')}
                    description={t('security.account.delete.dialog.loading')}
                />

                <div className="grid gap-3">
                    {SKELETON_ROWS.map((row) => (
                        <div key={row} className="flex items-center gap-3">
                            <Skeleton className="size-8 shrink-0" />
                            <Skeleton className="h-4 flex-1" />
                        </div>
                    ))}
                </div>
            </div>

            <AlertDialogFooter className={FOOTER_CLASSES}>
                <CancelButton label={tCommon('actions.cancel')} />
            </AlertDialogFooter>
        </>
    );
}

function PreviewFailed({ isRetrying, onRetry }: { isRetrying: boolean; onRetry: () => void }) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');

    return (
        <>
            <div className={BODY_CLASSES}>
                <DialogHeader
                    icon={TriangleAlert}
                    title={t('security.account.delete.dialog.title')}
                    description={t('security.account.delete.dialog.loadFailed')}
                />
            </div>

            <AlertDialogFooter className={FOOTER_CLASSES}>
                <CancelButton label={tCommon('actions.cancel')} />

                <Button
                    type="button"
                    variant="outline"
                    onClick={onRetry}
                    disabled={isRetrying}
                    aria-busy={isRetrying}
                    className={FOOTER_BUTTON_CLASSES}
                >
                    {isRetrying ? tCommon('actions.retrying') : tCommon('actions.tryAgain')}
                </Button>
            </AlertDialogFooter>
        </>
    );
}

function DeletionBlocked() {
    const { t } = useTranslation('admin');

    return (
        <>
            <div className={BODY_CLASSES}>
                <DialogHeader
                    icon={CalendarClock}
                    title={t('security.account.delete.blocked.title')}
                    description={t('security.account.delete.blocked.body')}
                />
            </div>

            <AlertDialogFooter className={FOOTER_CLASSES}>
                <CancelButton label={t('security.account.delete.blocked.acknowledge')} />
            </AlertDialogFooter>
        </>
    );
}

function DeletionConfirmation({ preview }: { preview: AccountDeletionPreview }) {
    const { t, i18n } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');
    const form = useDeleteAccountForm({ hasPassword: preview.has_password });
    const graceDate = fullDateFormatter(i18n.language).format(new Date(preview.grace_period_ends_at));
    const business = preview.owned_business;

    if (form.wasBlocked) {
        return <DeletionBlocked />;
    }

    return (
        <form onSubmit={form.submit} className="flex min-h-0 flex-1 flex-col">
            <div className={BODY_CLASSES}>
                <DialogHeader
                    icon={TriangleAlert}
                    title={
                        business === null
                            ? t('security.account.delete.dialog.title')
                            : t('security.account.delete.dialog.ownerTitle', { business: business.name })
                    }
                    description={
                        business === null
                            ? t('security.account.delete.regular.description')
                            : t('security.account.delete.owner.description', { business: business.name })
                    }
                />

                {business === null ? (
                    <AccountConsequences graceDate={graceDate} />
                ) : (
                    <OwnerConsequences
                        businessName={business.name}
                        upcomingAppointmentsCount={preview.upcoming_appointments_count}
                        graceDate={graceDate}
                    />
                )}

                {preview.has_password ? (
                    <FormField
                        id="delete-account-password"
                        type="password"
                        autoComplete="current-password"
                        required
                        label={t('security.account.delete.confirmation.password')}
                        value={form.confirmation}
                        onChange={(event) => form.update(event.target.value)}
                        error={form.error}
                    />
                ) : (
                    <FormField
                        id="delete-account-email"
                        type="email"
                        inputMode="email"
                        autoComplete="off"
                        autoCapitalize="none"
                        autoCorrect="off"
                        spellCheck={false}
                        required
                        label={
                            <span className="min-w-0 leading-snug wrap-anywhere">
                                {t('security.account.delete.confirmation.email', { email: preview.email })}
                            </span>
                        }
                        value={form.confirmation}
                        onChange={(event) => form.update(event.target.value)}
                        error={form.error}
                    />
                )}
            </div>

            <AlertDialogFooter className={FOOTER_CLASSES}>
                <CancelButton label={tCommon('actions.cancel')} disabled={form.isSubmitting} />

                <SubmitButton
                    variant="destructive"
                    className={FOOTER_BUTTON_CLASSES}
                    label={t('security.account.delete.action')}
                    submittingLabel={t('security.account.delete.deleting')}
                    isSubmitting={form.isSubmitting}
                    disabled={! form.canSubmit}
                />
            </AlertDialogFooter>
        </form>
    );
}

function DeletionPreviewContent() {
    const preview = useAccountDeletionPreview({ enabled: true });

    if (preview.isPending) {
        return <PreviewLoading />;
    }

    if (preview.isError) {
        return <PreviewFailed isRetrying={preview.isFetching} onRetry={() => void preview.refetch()} />;
    }

    if (preview.data.blocked_by !== null) {
        return <DeletionBlocked />;
    }

    return <DeletionConfirmation preview={preview.data} />;
}

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function DeleteAccountDialog({ open, onOpenChange }: Props) {
    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent className={CONTENT_CLASSES}>
                <DeletionPreviewContent />
            </AlertDialogContent>
        </AlertDialog>
    );
}

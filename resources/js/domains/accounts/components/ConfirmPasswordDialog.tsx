import { LockKeyhole } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { FormField } from '@/components/form/FormField';
import { SubmitButton } from '@/components/form/SubmitButton';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useConfirmPasswordForm } from './use-confirm-password-form';
import type { PasswordConfirmationDialogProps } from './use-password-confirmation';

const CONTENT_CLASSES =
    'top-auto bottom-0 flex max-h-[min(92svh,32rem)] max-w-none translate-y-0 flex-col gap-0 overflow-hidden rounded-b-none p-0 sm:top-1/2 sm:bottom-auto sm:max-w-md sm:-translate-y-1/2 sm:rounded-b-xl';

const BODY_CLASSES = 'grid min-h-0 flex-1 content-start gap-5 overflow-y-auto overscroll-contain p-4 sm:p-5';

const FOOTER_CLASSES =
    'mx-0 mb-0 shrink-0 rounded-b-none px-4 pt-4 pb-[calc(1rem+env(safe-area-inset-bottom))] sm:rounded-b-xl sm:px-5 sm:pb-4';

const FOOTER_BUTTON_CLASSES = 'h-11 px-4 md:h-9';

function ConfirmPasswordForm({ onConfirmed }: { onConfirmed: () => void }) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');
    const form = useConfirmPasswordForm({ onConfirmed });

    return (
        <form onSubmit={form.submit} className="flex min-h-0 flex-1 flex-col">
            <div className={BODY_CLASSES}>
                <DialogHeader className="gap-3">
                    <span className="grid size-10 place-items-center rounded-lg bg-muted">
                        <LockKeyhole aria-hidden="true" className="size-5 text-foreground" />
                    </span>

                    <DialogTitle>{t('security.confirmPassword.title')}</DialogTitle>

                    <DialogDescription className="text-pretty">{t('security.confirmPassword.body')}</DialogDescription>
                </DialogHeader>

                <FormField
                    id="confirm-password"
                    type="password"
                    autoComplete="current-password"
                    autoFocus
                    required
                    label={t('security.confirmPassword.label')}
                    value={form.password}
                    onChange={(event) => form.update(event.target.value)}
                    error={form.error}
                />
            </div>

            <DialogFooter className={FOOTER_CLASSES}>
                <DialogClose asChild>
                    <Button type="button" variant="ghost" disabled={form.isSubmitting} className={FOOTER_BUTTON_CLASSES}>
                        {tCommon('actions.cancel')}
                    </Button>
                </DialogClose>

                <SubmitButton
                    variant="brand"
                    className={FOOTER_BUTTON_CLASSES}
                    label={t('security.confirmPassword.submit')}
                    submittingLabel={t('security.confirmPassword.submitting')}
                    isSubmitting={form.isSubmitting}
                    disabled={! form.canSubmit}
                />
            </DialogFooter>
        </form>
    );
}

export function ConfirmPasswordDialog({ open, onOpenChange, onConfirmed }: PasswordConfirmationDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent showCloseButton={false} className={CONTENT_CLASSES}>
                <ConfirmPasswordForm onConfirmed={onConfirmed} />
            </DialogContent>
        </Dialog>
    );
}

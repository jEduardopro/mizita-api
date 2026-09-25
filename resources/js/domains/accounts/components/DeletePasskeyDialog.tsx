import { Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogMedia,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { formMessageFrom } from '@/lib/http';
import { raiseErrorToast, raiseSuccessToast } from '@/lib/toast';
import { useDeletePasskey } from '../queries';
import { ConfirmPasswordDialog } from './ConfirmPasswordDialog';
import { usePasswordConfirmation } from './use-password-confirmation';

const FOOTER_BUTTON_CLASSES = 'h-11 px-4 md:h-9';

type Props = {
    passkeyId: string;
    name: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function DeletePasskeyDialog({ passkeyId, name, open, onOpenChange }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');
    const gate = usePasswordConfirmation();
    const deletePasskey = useDeletePasskey();
    const isDeleting = gate.isChecking || deletePasskey.isPending;

    async function confirm() {
        try {
            const outcome = await gate.confirmThen(() => deletePasskey.mutateAsync(passkeyId));

            if (outcome.status === 'cancelled') {
                return;
            }

            raiseSuccessToast(t('security.passkey.deleted'));
            onOpenChange(false);
        } catch (error) {
            raiseErrorToast(formMessageFrom(error, t('security.passkey.deleteFailed')));
        }
    }

    return (
        <>
            <AlertDialog open={open} onOpenChange={onOpenChange}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogMedia>
                            <Trash2 aria-hidden="true" className="text-destructive" />
                        </AlertDialogMedia>

                        <AlertDialogTitle className="break-words">
                            {t('security.passkey.deleteConfirm.title', { name })}
                        </AlertDialogTitle>

                        <AlertDialogDescription>{t('security.passkey.deleteConfirm.body')}</AlertDialogDescription>
                    </AlertDialogHeader>

                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={isDeleting} className={FOOTER_BUTTON_CLASSES}>
                            {tCommon('actions.cancel')}
                        </AlertDialogCancel>

                        <AlertDialogAction
                            variant="destructive"
                            disabled={isDeleting}
                            aria-busy={isDeleting}
                            onClick={(event) => {
                                event.preventDefault();
                                void confirm();
                            }}
                            className={FOOTER_BUTTON_CLASSES}
                        >
                            {isDeleting ? t('security.passkey.deleting') : t('security.passkey.deleteConfirm.action')}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <ConfirmPasswordDialog {...gate.dialog} />
        </>
    );
}

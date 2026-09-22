import { Ban } from 'lucide-react';
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
import { useServerErrors } from '@/hooks/use-server-errors';
import { raiseSuccessToast } from '@/lib/toast';
import { useVoidPaymentTransaction } from '../queries';

type Props = {
    appointmentId: string;
    paymentId: string;
    transactionId: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onChanged: () => void;
};

export function VoidTransactionDialog({
    appointmentId,
    paymentId,
    transactionId,
    open,
    onOpenChange,
    onChanged,
}: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');
    const voidTransaction = useVoidPaymentTransaction(appointmentId);
    const { capture } = useServerErrors();

    async function confirm() {
        try {
            await voidTransaction.mutateAsync({ paymentId, transactionId });
            raiseSuccessToast(t('payments.toasts.voided'));
            onChanged();
            onOpenChange(false);
        } catch (error) {
            capture(error, t('payments.errors.voidFailed'));
        }
    }

    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogMedia>
                        <Ban aria-hidden="true" className="text-destructive" />
                    </AlertDialogMedia>

                    <AlertDialogTitle>{t('payments.void.title')}</AlertDialogTitle>

                    <AlertDialogDescription>{t('payments.void.description')}</AlertDialogDescription>
                </AlertDialogHeader>

                <AlertDialogFooter>
                    <AlertDialogCancel className="h-11 px-4 md:h-9">
                        {tCommon('actions.cancel')}
                    </AlertDialogCancel>

                    <AlertDialogAction
                        variant="destructive"
                        disabled={voidTransaction.isPending}
                        onClick={(event) => {
                            event.preventDefault();
                            void confirm();
                        }}
                        className="h-11 px-4 md:h-9"
                    >
                        {t('payments.void.confirm')}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

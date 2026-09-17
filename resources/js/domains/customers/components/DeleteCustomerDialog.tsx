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
import { useDeleteCustomer } from '../queries';
import type { Customer } from '../types';

type Props = {
    customer: Customer;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function DeleteCustomerDialog({ customer, open, onOpenChange }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');
    const deleteCustomer = useDeleteCustomer();

    async function confirm() {
        try {
            await deleteCustomer.mutateAsync(customer.id);
            raiseSuccessToast(t('customers.toasts.deleted'));
            onOpenChange(false);
        } catch (error) {
            raiseErrorToast(formMessageFrom(error, t('customers.errors.deleteFailed')));
        }
    }

    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogMedia>
                        <Trash2 aria-hidden="true" className="text-destructive" />
                    </AlertDialogMedia>

                    <AlertDialogTitle>{t('customers.delete.title')}</AlertDialogTitle>

                    <AlertDialogDescription>
                        {t('customers.delete.body', { name: customer.name })}
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <AlertDialogFooter>
                    <AlertDialogCancel className="h-11 px-4 md:h-9">
                        {tCommon('actions.cancel')}
                    </AlertDialogCancel>

                    <AlertDialogAction
                        variant="destructive"
                        disabled={deleteCustomer.isPending}
                        onClick={(event) => {
                            event.preventDefault();
                            void confirm();
                        }}
                        className="h-11 px-4 md:h-9"
                    >
                        {tCommon('actions.delete')}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

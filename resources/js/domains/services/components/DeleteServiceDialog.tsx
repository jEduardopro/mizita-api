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
import { useDeleteService } from '../queries';
import type { Service } from '../types';

type Props = {
    service: Service;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function DeleteServiceDialog({ service, open, onOpenChange }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');
    const deleteService = useDeleteService();

    async function confirm() {
        try {
            await deleteService.mutateAsync(service.id);
            raiseSuccessToast(t('services.toasts.deleted'));
            onOpenChange(false);
        } catch (error) {
            raiseErrorToast(formMessageFrom(error, t('services.errors.deleteFailed')));
        }
    }

    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogMedia>
                        <Trash2 aria-hidden="true" className="text-destructive" />
                    </AlertDialogMedia>

                    <AlertDialogTitle>{t('services.delete.title')}</AlertDialogTitle>

                    <AlertDialogDescription>
                        {t('services.delete.body', { name: service.name })}
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <AlertDialogFooter>
                    <AlertDialogCancel className="h-11 px-4 md:h-9">
                        {tCommon('actions.cancel')}
                    </AlertDialogCancel>

                    <AlertDialogAction
                        variant="destructive"
                        disabled={deleteService.isPending}
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

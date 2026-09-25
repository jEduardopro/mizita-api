import { UserMinus } from 'lucide-react';
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
import { useRemoveTeamMember } from '../queries';

type Props = {
    memberId: string;
    name: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function RemoveTeamMemberDialog({ memberId, name, open, onOpenChange }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');
    const removeTeamMember = useRemoveTeamMember();

    async function confirm() {
        try {
            await removeTeamMember.mutateAsync(memberId);
            raiseSuccessToast(t('team.toasts.removed'));
            onOpenChange(false);
        } catch (error) {
            raiseErrorToast(formMessageFrom(error, t('team.errors.removeFailed')));
        }
    }

    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogMedia>
                        <UserMinus aria-hidden="true" className="text-destructive" />
                    </AlertDialogMedia>

                    <AlertDialogTitle>{t('team.remove.title')}</AlertDialogTitle>

                    <AlertDialogDescription>{t('team.remove.body', { name })}</AlertDialogDescription>
                </AlertDialogHeader>

                <AlertDialogFooter>
                    <AlertDialogCancel className="h-11 px-4 md:h-9">
                        {tCommon('actions.cancel')}
                    </AlertDialogCancel>

                    <AlertDialogAction
                        variant="destructive"
                        disabled={removeTeamMember.isPending}
                        onClick={(event) => {
                            event.preventDefault();
                            void confirm();
                        }}
                        className="h-11 px-4 md:h-9"
                    >
                        {t('team.actions.remove')}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

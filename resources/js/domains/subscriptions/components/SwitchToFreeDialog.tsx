import { CalendarClock } from 'lucide-react';
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
import { useSwitchToFreePlan } from '../queries';

const FOOTER_BUTTON_CLASSES = 'h-11 px-4 md:h-9';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    accessEndsOn: string | null;
};

export function SwitchToFreeDialog({ open, onOpenChange, accessEndsOn }: Props) {
    const { t } = useTranslation('admin');
    const switchToFree = useSwitchToFreePlan();
    const isSwitching = switchToFree.isPending;

    const body = accessEndsOn === null
        ? t('plan.subscription.switchToFree.bodyUndated')
        : t('plan.subscription.switchToFree.body', { date: accessEndsOn });

    const doneMessage = accessEndsOn === null
        ? t('plan.subscription.switchToFree.doneUndated')
        : t('plan.subscription.switchToFree.done', { date: accessEndsOn });

    function handleOpenChange(next: boolean) {
        if (isSwitching) {
            return;
        }

        onOpenChange(next);
    }

    async function confirm() {
        try {
            await switchToFree.mutateAsync();

            raiseSuccessToast(doneMessage);
            onOpenChange(false);
        } catch (error) {
            raiseErrorToast(formMessageFrom(error, t('plan.subscription.switchToFree.failed')));
        }
    }

    return (
        <AlertDialog open={open} onOpenChange={handleOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogMedia>
                        <CalendarClock aria-hidden="true" />
                    </AlertDialogMedia>

                    <AlertDialogTitle>{t('plan.subscription.switchToFree.title')}</AlertDialogTitle>

                    <AlertDialogDescription className="text-pretty">{body}</AlertDialogDescription>
                </AlertDialogHeader>

                <AlertDialogFooter>
                    <AlertDialogCancel disabled={isSwitching} className={FOOTER_BUTTON_CLASSES}>
                        {t('plan.subscription.switchToFree.keep')}
                    </AlertDialogCancel>

                    <AlertDialogAction
                        variant="outline"
                        disabled={isSwitching}
                        aria-busy={isSwitching}
                        onClick={(event) => {
                            event.preventDefault();
                            void confirm();
                        }}
                        className={FOOTER_BUTTON_CLASSES}
                    >
                        {isSwitching
                            ? t('plan.subscription.switchToFree.switching')
                            : t('plan.settings.switchToFree')}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

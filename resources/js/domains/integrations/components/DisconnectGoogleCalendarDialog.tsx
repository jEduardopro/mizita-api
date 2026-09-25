import { Unplug } from 'lucide-react';
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
import { useDisconnectGoogleCalendar } from '../queries';

const FOOTER_BUTTON_CLASSES = 'h-11 px-4 md:h-9';

type Props = {
    calendarName: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function DisconnectGoogleCalendarDialog({ calendarName, open, onOpenChange }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');
    const disconnect = useDisconnectGoogleCalendar();
    const isDisconnecting = disconnect.isPending;

    async function confirm() {
        try {
            await disconnect.mutateAsync();

            raiseSuccessToast(t('integrations.googleCalendar.disconnected'));
            onOpenChange(false);
        } catch (error) {
            raiseErrorToast(formMessageFrom(error, t('integrations.googleCalendar.disconnectFailed')));
        }
    }

    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogMedia>
                        <Unplug aria-hidden="true" className="text-destructive" />
                    </AlertDialogMedia>

                    <AlertDialogTitle>{t('integrations.googleCalendar.disconnectConfirm.title')}</AlertDialogTitle>

                    <AlertDialogDescription className="break-words">
                        {t('integrations.googleCalendar.disconnectConfirm.body', { calendar: calendarName })}
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <AlertDialogFooter>
                    <AlertDialogCancel disabled={isDisconnecting} className={FOOTER_BUTTON_CLASSES}>
                        {tCommon('actions.cancel')}
                    </AlertDialogCancel>

                    <AlertDialogAction
                        variant="destructive"
                        disabled={isDisconnecting}
                        aria-busy={isDisconnecting}
                        onClick={(event) => {
                            event.preventDefault();
                            void confirm();
                        }}
                        className={FOOTER_BUTTON_CLASSES}
                    >
                        {isDisconnecting
                            ? t('integrations.googleCalendar.disconnecting')
                            : t('integrations.googleCalendar.disconnectConfirm.action')}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

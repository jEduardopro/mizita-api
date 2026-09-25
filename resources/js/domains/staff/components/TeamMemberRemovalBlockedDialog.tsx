import { CalendarClock } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogMedia,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function TeamMemberRemovalBlockedDialog({ open, onOpenChange }: Props) {
    const { t } = useTranslation('admin');

    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogMedia>
                        <CalendarClock aria-hidden="true" />
                    </AlertDialogMedia>

                    <AlertDialogTitle>{t('team.removeBlocked.title')}</AlertDialogTitle>

                    <AlertDialogDescription>{t('team.removeBlocked.body')}</AlertDialogDescription>
                </AlertDialogHeader>

                <AlertDialogFooter>
                    <AlertDialogAction className="h-11 px-4 md:h-9">
                        {t('team.removeBlocked.acknowledge')}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

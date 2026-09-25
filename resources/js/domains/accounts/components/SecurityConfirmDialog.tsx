import type { LucideIcon } from 'lucide-react';
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

const CONTENT_CLASSES =
    'top-auto bottom-0 flex max-h-[min(92svh,32rem)] translate-y-0 flex-col gap-0 overflow-hidden rounded-b-none p-0 data-[size=default]:max-w-none sm:top-1/2 sm:bottom-auto sm:-translate-y-1/2 sm:rounded-b-xl data-[size=default]:sm:max-w-md';

const BODY_CLASSES = 'grid min-h-0 flex-1 content-start gap-5 overflow-y-auto overscroll-contain p-4 sm:p-5';

const FOOTER_CLASSES =
    'mx-0 mb-0 shrink-0 rounded-b-none px-4 pt-4 pb-[calc(1rem+env(safe-area-inset-bottom))] sm:rounded-b-xl sm:px-5 sm:pb-4';

const FOOTER_BUTTON_CLASSES = 'h-11 px-4 md:h-9';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    icon: LucideIcon;
    title: string;
    body: string;
    actionLabel: string;
    onConfirm: () => void;
};

export function SecurityConfirmDialog({ open, onOpenChange, icon: Icon, title, body, actionLabel, onConfirm }: Props) {
    const { t } = useTranslation('common');

    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent className={CONTENT_CLASSES}>
                <div className={BODY_CLASSES}>
                    <AlertDialogHeader>
                        <AlertDialogMedia>
                            <Icon aria-hidden="true" className="text-destructive" />
                        </AlertDialogMedia>

                        <AlertDialogTitle>{title}</AlertDialogTitle>

                        <AlertDialogDescription>{body}</AlertDialogDescription>
                    </AlertDialogHeader>
                </div>

                <AlertDialogFooter className={FOOTER_CLASSES}>
                    <AlertDialogCancel className={FOOTER_BUTTON_CLASSES}>{t('actions.cancel')}</AlertDialogCancel>

                    <AlertDialogAction variant="destructive" onClick={onConfirm} className={FOOTER_BUTTON_CLASSES}>
                        {actionLabel}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

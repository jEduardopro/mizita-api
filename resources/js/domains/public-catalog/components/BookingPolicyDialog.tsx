import { cn } from 'cn';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { policyCancellationNote } from './booking/cancellation-window';

const CONTENT_CLASSES =
    'top-auto bottom-0 flex max-h-[92svh] max-w-none translate-y-0 flex-col gap-4 rounded-b-none p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] sm:top-1/2 sm:bottom-auto sm:max-h-[min(85svh,36rem)] sm:max-w-md sm:-translate-y-1/2 sm:rounded-b-xl sm:p-6';

type Props = {
    cancellationWindowMinutes: number | null;
    themeScope: string | undefined;
    children: ReactNode;
};

export function BookingPolicyDialog({ cancellationWindowMinutes, themeScope, children }: Props) {
    const { t } = useTranslation('public');

    return (
        <Dialog>
            <DialogTrigger asChild>{children}</DialogTrigger>

            <DialogContent showCloseButton={false} className={cn(CONTENT_CLASSES, themeScope)}>
                <DialogTitle className="text-base leading-snug font-semibold text-balance">
                    {t('booking.policy.dialogTitle')}
                </DialogTitle>

                <DialogDescription asChild>
                    <p className="min-h-0 overflow-y-auto rounded-xl bg-muted/50 p-4 text-sm leading-relaxed text-pretty text-foreground">
                        <strong className="font-semibold">
                            {t('booking.policy.cancellationTitle')}
                        </strong>{' '}
                        {policyCancellationNote(cancellationWindowMinutes, t)}
                    </p>
                </DialogDescription>

                <div className="flex justify-end">
                    <DialogClose asChild>
                        <Button type="button" variant="outline" className="h-11 rounded-full px-5">
                            {t('booking.policy.acknowledge')}
                        </Button>
                    </DialogClose>
                </div>
            </DialogContent>
        </Dialog>
    );
}

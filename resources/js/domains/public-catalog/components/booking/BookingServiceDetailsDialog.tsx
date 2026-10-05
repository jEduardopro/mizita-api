import { cn } from 'cn';
import { X } from 'lucide-react';
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
import {
    BUTTON_SHAPE_CLASSES,
    type BrandColorClasses,
    type ButtonShape,
} from '@/lib/booking-brand';
import { BOOKING_CTA_CLASS } from '../BookingCta';

const CONTENT_CLASSES =
    'top-auto bottom-0 flex max-h-[92svh] max-w-none translate-y-0 flex-col gap-0 overflow-hidden rounded-b-none p-0 sm:top-1/2 sm:bottom-auto sm:max-h-[min(85svh,40rem)] sm:max-w-lg sm:-translate-y-1/2 sm:rounded-b-xl';

type Props = {
    name: string;
    summary: string;
    description: string;
    accent: BrandColorClasses;
    buttonShape: ButtonShape;
    themeScope: string | undefined;
    onChoose(): void;
    children: ReactNode;
};

export function BookingServiceDetailsDialog({
    name,
    summary,
    description,
    accent,
    buttonShape,
    themeScope,
    onChoose,
    children,
}: Props) {
    const { t } = useTranslation('public');

    return (
        <Dialog>
            <DialogTrigger asChild>{children}</DialogTrigger>

            <DialogContent showCloseButton={false} className={cn(CONTENT_CLASSES, themeScope)}>
                <div className="flex items-start gap-3 px-5 pt-5 pb-3 sm:px-6 sm:pt-6">
                    <div className="grid min-w-0 flex-1 gap-1">
                        <DialogTitle className="font-heading text-lg leading-snug font-semibold tracking-[-0.01em] text-balance break-words">
                            {name}
                        </DialogTitle>

                        <p className="text-sm text-muted-foreground">{summary}</p>
                    </div>

                    <DialogClose asChild>
                        <Button variant="ghost" className="-mt-2 -mr-2.5 size-11 shrink-0 rounded-full p-0">
                            <X aria-hidden="true" className="size-5" />
                            <span className="sr-only">{t('booking.services.closeDetails')}</span>
                        </Button>
                    </DialogClose>
                </div>

                <DialogDescription asChild>
                    <p className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 pb-5 text-[0.9375rem] leading-relaxed whitespace-pre-line text-pretty text-foreground sm:px-6">
                        {description}
                    </p>
                </DialogDescription>

                <div className="border-t border-border px-5 pt-4 pb-[max(1.25rem,env(safe-area-inset-bottom))] sm:flex sm:justify-end sm:px-6 sm:pb-6">
                    <DialogClose asChild>
                        <button
                            type="button"
                            onClick={onChoose}
                            className={cn(
                                BOOKING_CTA_CLASS,
                                'w-full sm:w-auto',
                                accent.accent,
                                accent.accentForeground,
                                BUTTON_SHAPE_CLASSES[buttonShape],
                            )}
                        >
                            {t('booking.services.choose')}
                        </button>
                    </DialogClose>
                </div>
            </DialogContent>
        </Dialog>
    );
}

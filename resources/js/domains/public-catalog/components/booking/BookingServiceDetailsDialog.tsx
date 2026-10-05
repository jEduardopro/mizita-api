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
    imageUrl: string | null;
    duration: string;
    price: string;
    bufferMinutes: number;
    description: string | null;
    accent: BrandColorClasses;
    buttonShape: ButtonShape;
    themeScope: string | undefined;
    onChoose(): void;
    children: ReactNode;
};

export function BookingServiceDetailsDialog({
    name,
    imageUrl,
    duration,
    price,
    bufferMinutes,
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
                <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain">
                    {imageUrl === null ? null : (
                        <img
                            src={imageUrl}
                            alt=""
                            decoding="async"
                            className="aspect-4/3 w-full object-cover"
                        />
                    )}

                    <div className="sticky top-0 z-10 flex items-start gap-3 bg-popover px-5 pt-5 pb-3 sm:px-6 sm:pt-6">
                        <DialogTitle className="min-w-0 flex-1 font-heading text-lg leading-snug font-semibold tracking-[-0.01em] text-balance break-words">
                            {name}
                        </DialogTitle>

                        <DialogClose asChild>
                            <Button variant="ghost" className="-mt-2 -mr-2.5 size-11 shrink-0 rounded-full p-0">
                                <X aria-hidden="true" className="size-5" />
                                <span className="sr-only">{t('booking.services.closeDetails')}</span>
                            </Button>
                        </DialogClose>
                    </div>

                    <div className="grid gap-5 px-5 pb-5 sm:px-6">
                        <DialogDescription asChild>
                            <dl className="flex flex-wrap gap-x-8 gap-y-3 text-foreground">
                                <ServiceFact label={t('booking.services.durationLabel')} value={duration} />

                                <ServiceFact label={t('booking.services.priceLabel')} value={price} />

                                {bufferMinutes > 0 ? (
                                    <ServiceFact
                                        label={t('booking.services.bufferLabel')}
                                        value={t('booking.services.buffer', { count: bufferMinutes })}
                                    />
                                ) : null}
                            </dl>
                        </DialogDescription>

                        {description === null ? null : (
                            <p className="text-[0.9375rem] leading-relaxed whitespace-pre-line text-pretty text-foreground">
                                {description}
                            </p>
                        )}
                    </div>
                </div>

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

type ServiceFactProps = {
    label: string;
    value: string;
};

function ServiceFact({ label, value }: ServiceFactProps) {
    return (
        <div className="grid gap-1">
            <dt className="text-[0.6875rem] font-medium tracking-[0.1em] text-muted-foreground uppercase">
                {label}
            </dt>

            <dd className="text-[0.9375rem] font-medium tabular-nums">{value}</dd>
        </div>
    );
}

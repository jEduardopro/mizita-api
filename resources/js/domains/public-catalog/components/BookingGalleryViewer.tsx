import { cn } from 'cn';
import { X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Carousel,
    CarouselContent,
    CarouselItem,
    CarouselNext,
    CarouselPrevious,
} from '@/components/ui/carousel';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { GalleryImage } from '@/lib/booking-brand';
import { BookingCarouselCounter } from './BookingCarouselCounter';
import type { GalleryViewerState } from './use-gallery-viewer';

const STAGE_CONTROL_CLASSES =
    'size-11 text-white hover:bg-white/15 hover:text-white dark:hover:bg-white/15 focus-visible:ring-white/60';

const STAGE_ARROW_CLASSES = cn(
    STAGE_CONTROL_CLASSES,
    'rounded-full bg-black/45 backdrop-blur-sm disabled:opacity-0 [&_svg]:size-5',
);

type Props = GalleryViewerState & {
    images: GalleryImage[];
    businessName: string;
    themeScope: string | undefined;
};

export function BookingGalleryViewer({
    images,
    businessName,
    themeScope,
    open,
    startIndex,
    onOpenChange,
}: Props) {
    const { t } = useTranslation('public');
    const total = images.length;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                showCloseButton={false}
                className={cn(
                    'flex h-svh max-h-none w-screen max-w-none flex-col gap-0 overflow-hidden rounded-none bg-black p-0 text-white ring-0 sm:h-[88svh] sm:w-[calc(100%-4rem)] sm:max-w-5xl sm:rounded-2xl',
                    themeScope,
                )}
            >
                <Carousel
                    opts={{ startIndex, loop: true }}
                    aria-label={t('booking.gallery.description', { name: businessName })}
                    className="flex min-h-0 flex-1 flex-col"
                >
                    <DialogHeader className="flex-row items-center justify-between gap-3 px-4 pt-[max(0.5rem,env(safe-area-inset-top))] pb-2 sm:px-5 sm:pt-3">
                        <div className="flex min-w-0 items-baseline gap-3">
                            <DialogTitle className="truncate text-white">
                                {t('booking.gallery.title')}
                            </DialogTitle>

                            <BookingCarouselCounter
                                total={total}
                                className="text-sm tabular-nums text-white/65"
                            />
                        </div>

                        <DialogDescription className="sr-only">
                            {t('booking.gallery.description', { name: businessName })}
                        </DialogDescription>

                        <DialogClose asChild>
                            <Button variant="ghost" className={cn(STAGE_CONTROL_CLASSES, '-mr-2 shrink-0 p-0')}>
                                <X aria-hidden="true" className="size-5" />
                                <span className="sr-only">{t('booking.gallery.close')}</span>
                            </Button>
                        </DialogClose>
                    </DialogHeader>

                    <div className="relative min-h-0 flex-1 [&>[data-slot=carousel-content]]:h-full">
                        <CarouselContent className="ml-0 h-full">
                            {images.map((image, index) => (
                                <CarouselItem
                                    key={image.id}
                                    className="h-full px-2 pt-2 pb-[max(1rem,env(safe-area-inset-bottom))] sm:px-16 sm:pb-6"
                                >
                                    <img
                                        src={image.url}
                                        alt={t('booking.gallery.photoAlt', {
                                            position: index + 1,
                                            business: businessName,
                                        })}
                                        decoding="async"
                                        draggable={false}
                                        className="size-full object-contain select-none"
                                    />
                                </CarouselItem>
                            ))}
                        </CarouselContent>

                        {total > 1 ? (
                            <>
                                <CarouselPrevious
                                    variant="ghost"
                                    aria-label={t('booking.gallery.previous')}
                                    className={cn(STAGE_ARROW_CLASSES, 'left-2 sm:left-3')}
                                />
                                <CarouselNext
                                    variant="ghost"
                                    aria-label={t('booking.gallery.next')}
                                    className={cn(STAGE_ARROW_CLASSES, 'right-2 sm:right-3')}
                                />
                            </>
                        ) : null}
                    </div>
                </Carousel>
            </DialogContent>
        </Dialog>
    );
}

import { cn } from 'cn';
import { X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { GalleryImage } from '@/lib/booking-brand';

type Props = {
    images: GalleryImage[];
    businessName: string;
    themeScope: string | undefined;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function BookingGalleryLightbox({
    images,
    businessName,
    themeScope,
    open,
    onOpenChange,
}: Props) {
    const { t } = useTranslation('public');

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                showCloseButton={false}
                className={cn(
                    'flex h-svh max-h-none w-screen max-w-none flex-col gap-0 rounded-none bg-background p-0 text-foreground sm:h-[85svh] sm:w-full sm:max-w-3xl sm:rounded-2xl',
                    themeScope,
                )}
            >
                <DialogHeader className="flex-row items-center justify-between gap-3 border-b border-border px-5 py-3">
                    <DialogTitle>{t('booking.gallery.title')}</DialogTitle>

                    <DialogDescription className="sr-only">
                        {t('booking.gallery.description', { name: businessName })}
                    </DialogDescription>

                    <DialogClose asChild>
                        <Button variant="ghost" className="-mr-2 size-11 shrink-0 p-0">
                            <X aria-hidden="true" />
                            <span className="sr-only">{t('booking.gallery.close')}</span>
                        </Button>
                    </DialogClose>
                </DialogHeader>

                <ul className="min-h-0 flex-1 grid gap-3 overflow-y-auto overscroll-contain p-4 pb-[max(1rem,env(safe-area-inset-bottom))] content-start sm:grid-cols-2">
                    {images.map((image) => (
                        <li key={image.id}>
                            <img
                                src={image.url}
                                alt=""
                                loading="lazy"
                                className="w-full rounded-xl bg-muted"
                            />
                        </li>
                    ))}
                </ul>
            </DialogContent>
        </Dialog>
    );
}

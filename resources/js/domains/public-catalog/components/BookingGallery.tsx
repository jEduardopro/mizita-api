import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import type { GalleryImage } from '@/lib/booking-brand';
import { BookingGalleryViewer } from './BookingGalleryViewer';
import { useGalleryViewer } from './use-gallery-viewer';

const MOSAIC_LIMIT = 5;

const FIRST_PHOTO_INDEX = 0;

type Props = {
    images: GalleryImage[];
    businessName: string;
    themeScope: string | undefined;
};

export function BookingGallery({ images, businessName, themeScope }: Props) {
    const { t } = useTranslation('public');
    const { openAt, viewerState } = useGalleryViewer();

    const mosaic = images.slice(0, MOSAIC_LIMIT);
    const hiddenCount = images.length - mosaic.length;

    return (
        <div className="grid justify-items-start gap-4">
            <ul className="grid w-full grid-cols-2 gap-2 sm:grid-cols-4 sm:grid-rows-2">
                {mosaic.map((image, index) => {
                    const isLead = index === 0;
                    const isLastTile = index === mosaic.length - 1;
                    const position = index + 1;

                    return (
                        <li
                            key={image.id}
                            className={cn(
                                isLead && 'col-span-2 sm:row-span-2',
                                mosaic.length === 1 && 'sm:col-span-4 sm:row-span-1',
                            )}
                        >
                            <button
                                type="button"
                                onClick={() => openAt(index)}
                                className={cn(
                                    'group relative block size-full overflow-hidden rounded-xl bg-muted outline-none focus-visible:ring-3 focus-visible:ring-ring/50',
                                    isLead ? 'aspect-[16/10] sm:aspect-auto' : 'aspect-square',
                                )}
                            >
                                <img
                                    src={image.url}
                                    alt={t('booking.gallery.photoAlt', {
                                        position,
                                        business: businessName,
                                    })}
                                    loading="lazy"
                                    decoding="async"
                                    className="size-full object-cover transition-transform duration-300 motion-safe:group-hover:scale-[1.04]"
                                />

                                {isLastTile && hiddenCount > 0 ? (
                                    <span className="absolute inset-0 grid place-content-center bg-black/55 text-base font-medium text-white">
                                        {t('booking.gallery.more', { count: hiddenCount })}
                                    </span>
                                ) : null}
                            </button>
                        </li>
                    );
                })}
            </ul>

            <Button
                type="button"
                variant="outline"
                onClick={() => openAt(FIRST_PHOTO_INDEX)}
                className="h-11 px-4"
            >
                {t('booking.gallery.showAll')}
            </Button>

            <BookingGalleryViewer
                images={images}
                businessName={businessName}
                themeScope={themeScope}
                {...viewerState}
            />
        </div>
    );
}

import { useTranslation } from 'react-i18next';
import { Carousel, CarouselContent, CarouselItem } from '@/components/ui/carousel';
import type { GalleryImage } from '@/lib/booking-brand';
import { BookingCarouselCounter } from './BookingCarouselCounter';

const FIRST_SLIDE_INDEX = 0;

type Props = {
    slides: GalleryImage[];
    label: string;
    onOpenSlide: (index: number) => void;
};

export function BookingHeroCarousel({ slides, label, onOpenSlide }: Props) {
    const { t } = useTranslation('public');
    const total = slides.length;

    return (
        <Carousel
            aria-label={label}
            className="h-full [&>[data-slot=carousel-content]]:h-full"
        >
            <CarouselContent className="ml-0 h-full">
                {slides.map((slide, index) => (
                    <CarouselItem key={slide.id} className="h-full pl-0">
                        <button
                            type="button"
                            onClick={() => onOpenSlide(index)}
                            className="block size-full outline-none focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:ring-inset"
                        >
                            <img
                                src={slide.url}
                                alt=""
                                loading={index === FIRST_SLIDE_INDEX ? 'eager' : 'lazy'}
                                draggable={false}
                                className="size-full object-cover select-none"
                            />

                            <span className="sr-only">
                                {t('booking.gallery.openPhoto', { position: index + 1 })}
                            </span>
                        </button>
                    </CarouselItem>
                ))}
            </CarouselContent>

            {total > 1 ? (
                <BookingCarouselCounter
                    total={total}
                    className="pointer-events-none absolute top-3 right-3 rounded-full bg-black/55 px-2.5 py-1 text-xs font-medium tabular-nums text-white backdrop-blur-sm"
                />
            ) : null}
        </Carousel>
    );
}

import Autoplay from 'embla-carousel-autoplay';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Carousel, CarouselContent, CarouselItem } from '@/components/ui/carousel';
import type { GalleryImage } from '@/lib/booking-brand';
import { BookingCarouselDots } from './BookingCarouselDots';

const FIRST_SLIDE_INDEX = 0;
const SLIDE_DURATION_MS = 4000;
const REDUCED_MOTION_QUERY = '(prefers-reduced-motion: reduce)';
const CAROUSEL_OPTIONS = { loop: true };

function createHeroAutoplay() {
    return Autoplay({
        delay: SLIDE_DURATION_MS,
        stopOnInteraction: false,
        breakpoints: { [REDUCED_MOTION_QUERY]: { active: false } },
    });
}

type Props = {
    slides: GalleryImage[];
    businessName: string;
    onOpenSlide: (index: number) => void;
};

export function BookingHeroCarousel({ slides, businessName, onOpenSlide }: Props) {
    const { t } = useTranslation('public');
    const [autoplay] = useState(createHeroAutoplay);
    const hasMultipleSlides = slides.length > 1;

    const plugins = useMemo(
        () => (hasMultipleSlides ? [autoplay] : []),
        [autoplay, hasMultipleSlides],
    );

    return (
        <Carousel
            aria-label={t('booking.gallery.description', { name: businessName })}
            opts={CAROUSEL_OPTIONS}
            plugins={plugins}
            className="h-full [&>[data-slot=carousel-content]]:h-full"
        >
            <CarouselContent className="ml-0 h-full">
                {slides.map((slide, index) => {
                    const isFirstSlide = index === FIRST_SLIDE_INDEX;

                    return (
                        <CarouselItem key={slide.id} className="h-full pl-0">
                            <button
                                type="button"
                                onClick={() => onOpenSlide(index)}
                                className="block size-full outline-none focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:ring-inset"
                            >
                                <img
                                    src={slide.url}
                                    alt={t('booking.gallery.photoAlt', {
                                        position: index + 1,
                                        business: businessName,
                                    })}
                                    loading={isFirstSlide ? 'eager' : 'lazy'}
                                    fetchPriority={isFirstSlide ? 'high' : 'auto'}
                                    draggable={false}
                                    className="size-full object-cover select-none"
                                />
                            </button>
                        </CarouselItem>
                    );
                })}
            </CarouselContent>

            {hasMultipleSlides ? (
                <BookingCarouselDots
                    slideIds={slides.map((slide) => slide.id)}
                    onNavigate={autoplay.reset}
                />
            ) : null}
        </Carousel>
    );
}

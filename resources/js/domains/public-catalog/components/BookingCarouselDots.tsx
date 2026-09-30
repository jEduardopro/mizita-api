import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import { useCarousel } from '@/components/ui/carousel';
import { useCarouselAutoplaying } from './use-carousel-autoplaying';
import { useCarouselPosition } from './use-carousel-position';

type Props = {
    slideIds: string[];
    onNavigate: () => void;
};

export function BookingCarouselDots({ slideIds, onNavigate }: Props) {
    const { t } = useTranslation('public');
    const { api } = useCarousel();
    const position = useCarouselPosition();
    const autoplaying = useCarouselAutoplaying();
    const total = slideIds.length;

    function goToSlide(index: number) {
        api?.scrollTo(index);
        onNavigate();
    }

    return (
        <div className="absolute inset-x-5 bottom-5 z-10 flex justify-center">
            <div className="relative isolate flex min-w-0">
                <span
                    aria-hidden="true"
                    className="absolute inset-x-0 top-1/2 -z-10 h-4 -translate-y-1/2 rounded-full bg-black/40 backdrop-blur-sm"
                />

                {slideIds.map((slideId, index) => {
                    const isActive = index + 1 === position;

                    return (
                        <button
                            key={slideId}
                            type="button"
                            aria-label={t('booking.gallery.goToPhoto', { position: index + 1 })}
                            aria-current={isActive}
                            onClick={() => goToSlide(index)}
                            className="group flex h-11 w-6 min-w-0 touch-manipulation items-center justify-center outline-none"
                        >
                            <span
                                className={cn(
                                    'h-1.5 max-w-full rounded-full transition-[width,background-color] duration-300 motion-reduce:transition-none',
                                    'group-focus-visible:outline-2 group-focus-visible:outline-offset-2 group-focus-visible:outline-white',
                                    isActive ? 'w-3.5 bg-white' : 'w-1.5 bg-white/55',
                                )}
                            />
                        </button>
                    );
                })}
            </div>

            <span aria-live={autoplaying ? 'off' : 'polite'} className="sr-only">
                {t('booking.gallery.position', { position, total })}
            </span>
        </div>
    );
}

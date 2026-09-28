import { useTranslation } from 'react-i18next';
import { useCarouselPosition } from './use-carousel-position';

type Props = {
    total: number;
    className?: string;
};

export function BookingCarouselCounter({ total, className }: Props) {
    const { t } = useTranslation('public');
    const position = useCarouselPosition();

    return (
        <span aria-live="polite" className={className}>
            <span aria-hidden="true">{t('booking.gallery.counter', { position, total })}</span>
            <span className="sr-only">{t('booking.gallery.position', { position, total })}</span>
        </span>
    );
}

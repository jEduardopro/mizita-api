import { MapPin } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { PublicLocation } from '../types';
import { addressLineFrom, mapEmbedUrlFor, mapUrlFor } from './booking-address';

type Props = {
    location: PublicLocation;
};

export function BookingLocation({ location }: Props) {
    const { t } = useTranslation('public');

    return (
        <div className="grid gap-4">
            <a
                href={mapUrlFor(location)}
                target="_blank"
                rel="noopener noreferrer"
                className="flex items-start gap-2 justify-self-start rounded-lg py-3 text-[0.9375rem] leading-relaxed underline decoration-muted-foreground/50 underline-offset-4 transition-colors outline-none hover:decoration-foreground focus-visible:ring-3 focus-visible:ring-ring/50"
            >
                <MapPin aria-hidden="true" className="mt-1 size-4 shrink-0 text-muted-foreground" />

                <span>
                    {addressLineFrom(location)}

                    <span className="sr-only">{` (${t('booking.location.mapLink')})`}</span>
                </span>
            </a>

            <div className="aspect-[16/9] w-full min-w-0 overflow-hidden rounded-xl border border-border">
                <iframe
                    src={mapEmbedUrlFor(location)}
                    title={t('booking.location.mapTitle')}
                    loading="lazy"
                    referrerPolicy="no-referrer-when-downgrade"
                    className="size-full border-0"
                />
            </div>
        </div>
    );
}

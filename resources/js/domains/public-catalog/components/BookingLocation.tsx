import { ExternalLink, MapPin } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import type { PublicLocation } from '../types';
import { addressLinesFrom, mapUrlFor } from './booking-address';

type Props = {
    location: PublicLocation;
};

export function BookingLocation({ location }: Props) {
    const { t } = useTranslation('public');

    const lines = addressLinesFrom(location);
    const mapUrl = mapUrlFor(location);

    return (
        <div className="grid justify-items-start gap-4">
            <p className="flex items-start gap-2 text-[0.9375rem] leading-relaxed">
                <MapPin aria-hidden="true" className="mt-1 size-4 shrink-0 text-muted-foreground" />

                <span className="grid">
                    {lines.map((line) => (
                        <span key={line}>{line}</span>
                    ))}
                </span>
            </p>

            {mapUrl === null ? null : (
                <Button asChild variant="outline" className="h-11 gap-2 px-4">
                    <a href={mapUrl} target="_blank" rel="noopener noreferrer">
                        {t('booking.location.directions')}
                        <ExternalLink aria-hidden="true" />
                    </a>
                </Button>
            )}
        </div>
    );
}

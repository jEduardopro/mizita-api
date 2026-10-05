import { Accordion } from '@/components/ui/accordion';
import type { BrandColor, ButtonShape } from '@/lib/booking-brand';
import type { PublicService } from '../types';
import { BookingServiceRow } from './BookingServiceRow';

type Props = {
    slug: string;
    services: PublicService[];
    currencyCode: string;
    accentColor: BrandColor;
    buttonShape: ButtonShape;
};

export function BookingServices({
    slug,
    services,
    currencyCode,
    accentColor,
    buttonShape,
}: Props) {
    return (
        <Accordion type="single" collapsible>
            {services.map((service) => (
                <BookingServiceRow
                    key={service.id}
                    slug={slug}
                    service={service}
                    currencyCode={currencyCode}
                    accentColor={accentColor}
                    buttonShape={buttonShape}
                />
            ))}
        </Accordion>
    );
}

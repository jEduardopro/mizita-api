import { useEffect } from 'react';
import { Accordion } from '@/components/ui/accordion';
import type { BrandColor, ButtonShape } from '@/lib/booking-brand';
import type { PublicService } from '../types';
import { BOOKING_SECTION_IDS } from './booking-sections';
import { BookingServiceRow } from './BookingServiceRow';

type Props = {
    services: PublicService[];
    currencyCode: string;
    accentColor: BrandColor;
    buttonShape: ButtonShape;
    openServiceSlug: string | null;
};

function scrollToServices(): void {
    const section = document.getElementById(BOOKING_SECTION_IDS.services);

    if (section === null) {
        return;
    }

    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    section.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth' });
}

export function BookingServices({
    services,
    currencyCode,
    accentColor,
    buttonShape,
    openServiceSlug,
}: Props) {
    const openedService = services.find((service) => service.slug === openServiceSlug);

    useEffect(() => {
        if (openedService === undefined) {
            return;
        }

        scrollToServices();
    }, [openedService]);

    return (
        <Accordion type="single" collapsible defaultValue={openedService?.slug}>
            {services.map((service) => (
                <BookingServiceRow
                    key={service.id}
                    service={service}
                    currencyCode={currencyCode}
                    accentColor={accentColor}
                    buttonShape={buttonShape}
                />
            ))}
        </Accordion>
    );
}

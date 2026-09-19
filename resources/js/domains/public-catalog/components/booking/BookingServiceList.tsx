import { PackageOpen } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { brandColorClasses, type BrandColor, type ButtonShape } from '@/lib/booking-brand';
import type { PublicService } from '../../types';
import { BookingServiceOption } from './BookingServiceOption';

type Props = {
    slug: string;
    services: PublicService[];
    currencyCode: string;
    selectedServiceId: string | null;
    accentColor: BrandColor;
    buttonShape: ButtonShape;
    onSelect(serviceId: string): void;
};

export function BookingServiceList({
    slug,
    services,
    currencyCode,
    selectedServiceId,
    accentColor,
    buttonShape,
    onSelect,
}: Props) {
    const { t } = useTranslation('public');

    if (services.length === 0) {
        return (
            <div className="grid justify-items-center gap-3 rounded-xl border border-dashed border-border px-5 py-12 text-center">
                <PackageOpen aria-hidden="true" className="size-6 text-muted-foreground" />

                <p className="max-w-sm text-sm text-pretty text-muted-foreground">
                    {t('booking.flow.empty.services')}
                </p>
            </div>
        );
    }

    const accent = brandColorClasses[accentColor];

    return (
        <ul className="grid gap-3">
            {services.map((service) => (
                <BookingServiceOption
                    key={service.id}
                    slug={slug}
                    service={service}
                    currencyCode={currencyCode}
                    isSelected={service.id === selectedServiceId}
                    accent={accent}
                    buttonShape={buttonShape}
                    onSelect={onSelect}
                />
            ))}
        </ul>
    );
}

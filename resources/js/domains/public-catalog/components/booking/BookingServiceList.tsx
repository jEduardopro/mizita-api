import { PackageOpen } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import {
    brandColorClasses,
    THEME_SCOPES,
    type BrandColor,
    type ButtonShape,
    type PageTheme,
} from '@/lib/booking-brand';
import type { PublicService } from '../../types';
import { BookingServiceOption } from './BookingServiceOption';

type Props = {
    services: PublicService[];
    currencyCode: string;
    selectedServiceId: string | null;
    accentColor: BrandColor;
    buttonShape: ButtonShape;
    theme: PageTheme;
    onSelect(serviceId: string): void;
    emptyState?: ReactNode;
};

function NoServicesAvailable() {
    const { t } = useTranslation('public');

    return (
        <div className="grid justify-items-center gap-3 rounded-xl border border-dashed border-border px-5 py-12 text-center">
            <PackageOpen aria-hidden="true" className="size-6 text-muted-foreground" />

            <p className="max-w-sm text-sm text-pretty text-muted-foreground">
                {t('booking.flow.empty.services')}
            </p>
        </div>
    );
}

export function BookingServiceList({
    services,
    currencyCode,
    selectedServiceId,
    accentColor,
    buttonShape,
    theme,
    onSelect,
    emptyState = <NoServicesAvailable />,
}: Props) {
    if (services.length === 0) {
        return emptyState;
    }

    const accent = brandColorClasses[accentColor];
    const themeScope = THEME_SCOPES[theme];

    return (
        <ul className="grid gap-3">
            {services.map((service) => (
                <BookingServiceOption
                    key={service.id}
                    service={service}
                    currencyCode={currencyCode}
                    isSelected={service.id === selectedServiceId}
                    accent={accent}
                    buttonShape={buttonShape}
                    themeScope={themeScope}
                    onSelect={onSelect}
                />
            ))}
        </ul>
    );
}

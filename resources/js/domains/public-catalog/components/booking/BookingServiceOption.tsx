import { Link } from '@inertiajs/react';
import { cn } from 'cn';
import { ArrowUpRight, ChevronRight } from 'lucide-react';
import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import {
    BUTTON_SHAPE_CLASSES,
    type BrandColorClasses,
    type ButtonShape,
} from '@/lib/booking-brand';
import { formatMoney, isFreeAmount } from '@/lib/money';
import type { PublicService } from '../../types';

type Props = {
    slug: string;
    service: PublicService;
    currencyCode: string;
    isSelected: boolean;
    accent: BrandColorClasses;
    buttonShape: ButtonShape;
    onSelect(serviceId: string): void;
};

export function BookingServiceOption({
    slug,
    service,
    currencyCode,
    isSelected,
    accent,
    buttonShape,
    onSelect,
}: Props) {
    const { t, i18n } = useTranslation('public');
    const nameId = useId();

    const duration = t('booking.services.duration', { count: service.duration_minutes });
    const price = isFreeAmount(service.price)
        ? t('booking.services.free')
        : formatMoney(service.price, currencyCode, i18n.language);

    const hasDescription = service.description !== null;
    const detailsUrl = `/${encodeURIComponent(slug)}/${encodeURIComponent(service.slug)}`;

    return (
        <li className="relative">
            <button
                type="button"
                onClick={() => onSelect(service.id)}
                aria-current={isSelected ? true : undefined}
                className={cn(
                    'flex min-h-16 w-full items-center gap-4 border border-border px-5 py-3.5 text-left outline-none motion-safe:transition-colors focus-visible:ring-3 focus-visible:ring-ring/50',
                    BUTTON_SHAPE_CLASSES[buttonShape],
                    isSelected ? accent.surface : 'hover:bg-muted/60',
                    hasDescription && 'pb-13',
                )}
            >
                <span className="grid min-w-0 flex-1 gap-1">
                    <span
                        id={nameId}
                        className="text-[0.9375rem] leading-snug font-medium text-pretty"
                    >
                        {service.name}
                    </span>

                    <span className="text-sm text-muted-foreground">
                        {t('booking.services.summary', { duration, price })}
                    </span>
                </span>

                <ChevronRight
                    aria-hidden="true"
                    className={cn(
                        'size-5 shrink-0',
                        isSelected ? 'text-foreground' : 'text-muted-foreground',
                    )}
                />
            </button>

            {hasDescription ? (
                <Link
                    href={detailsUrl}
                    aria-describedby={nameId}
                    className="absolute bottom-1.5 left-3.5 z-10 inline-flex h-11 items-center gap-1 rounded-md px-1.5 text-sm text-muted-foreground underline underline-offset-4 outline-none hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50"
                >
                    {t('booking.services.details')}

                    <ArrowUpRight aria-hidden="true" className="size-3.5" />
                </Link>
            ) : null}
        </li>
    );
}

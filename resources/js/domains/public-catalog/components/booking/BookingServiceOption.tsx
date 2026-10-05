import { cn } from 'cn';
import { ChevronRight } from 'lucide-react';
import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import {
    BUTTON_SHAPE_CLASSES,
    type BrandColorClasses,
    type ButtonShape,
} from '@/lib/booking-brand';
import { formatMoney, isFreeAmount } from '@/lib/money';
import type { PublicService } from '../../types';
import { BookingServiceDetailsDialog } from './BookingServiceDetailsDialog';

type Props = {
    service: PublicService;
    currencyCode: string;
    isSelected: boolean;
    accent: BrandColorClasses;
    buttonShape: ButtonShape;
    themeScope: string | undefined;
    onSelect(serviceId: string): void;
};

export function BookingServiceOption({
    service,
    currencyCode,
    isSelected,
    accent,
    buttonShape,
    themeScope,
    onSelect,
}: Props) {
    const { t } = useTranslation('public');
    const nameId = useId();

    const duration = t('booking.services.duration', { count: service.duration_minutes });
    const price = isFreeAmount(service.price)
        ? t('booking.services.free')
        : formatMoney(service.price, currencyCode);
    const summary = t('booking.services.summary', { duration, price });

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
                    service.description !== null && 'pb-13',
                )}
            >
                <span className="grid min-w-0 flex-1 gap-1">
                    <span
                        id={nameId}
                        className="text-[0.9375rem] leading-snug font-medium text-pretty"
                    >
                        {service.name}
                    </span>

                    <span className="text-sm text-muted-foreground">{summary}</span>
                </span>

                <ChevronRight
                    aria-hidden="true"
                    className={cn(
                        'size-5 shrink-0',
                        isSelected ? 'text-foreground' : 'text-muted-foreground',
                    )}
                />
            </button>

            {service.description === null ? null : (
                <BookingServiceDetailsDialog
                    name={service.name}
                    summary={summary}
                    description={service.description}
                    accent={accent}
                    buttonShape={buttonShape}
                    themeScope={themeScope}
                    onChoose={() => onSelect(service.id)}
                >
                    <button
                        type="button"
                        aria-describedby={nameId}
                        className="absolute bottom-1.5 left-3.5 z-10 inline-flex h-11 items-center rounded-md px-1.5 text-sm text-muted-foreground underline underline-offset-4 outline-none hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50"
                    >
                        {t('booking.services.details')}
                    </button>
                </BookingServiceDetailsDialog>
            )}
        </li>
    );
}
